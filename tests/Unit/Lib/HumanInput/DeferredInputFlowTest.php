<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput;

use App\Tests\Unit\Lib\HumanInput\Fixture\InlineMcpHttpClient;
use App\Tests\Unit\Lib\HumanInput\Fixture\RecordingBus;
use App\Tests\Unit\Lib\HumanInput\Fixture\RecordingNotifier;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ApproveInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\StatelessClient;
use Leash\HumanInput\Client\CallerTokens;
use Leash\HumanInput\Client\HumanInputToolCaller;
use Leash\HumanInput\Client\InputOutcome;
use Leash\HumanInput\Client\InputRequestHandlerInterface;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Event\PendingInputResolved;
use Leash\HumanInput\Pending\DeferringInputRequestHandler;
use Leash\HumanInput\Pending\InMemoryPendingInputs;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Leash\HumanInput\Pending\ResumePendingInputHandler;
use Leash\HumanInput\RequestState\RejectionReason;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ElicitResult;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * M2 client side on the synthetic workflow: `input_required` → `PendingInput` + notification;
 * a human answers → resume with the original caller's token → result recorded → `PendingInputResolved`.
 */
final class DeferredInputFlowTest extends TestCase
{
    public const array TOKENS = ['bob' => 'token-bob', 'carol' => 'token-carol'];
    private const array ARGS = ['note' => 'ship it'];

    private Harness $harness;
    private Document $document;
    private InlineMcpHttpClient $http;
    private RawMcpClient $client;
    private InMemoryPendingInputs $pending;
    private RecordingNotifier $notifier;
    private RecordingBus $bus;
    /** @var list<PendingInputResolved> */
    private array $resolved = [];
    private ResumePendingInputHandler $resume;

    protected function setUp(): void
    {
        $this->harness = new Harness();
        $this->document = new Document('review');
        $workflow = DocumentWorkflow::create(approvalOnApprove: true);
        $users = array_flip(self::TOKENS);
        $this->http = new InlineMcpHttpClient(fn(string $token) => $this->harness->builder($workflow, $this->document, new InMemoryUser($users[$token], null, ['ROLE_EDITOR'])));
        $factory = new Psr17Factory();
        $this->client = new RawMcpClient($this->http, $factory, $factory);
        $this->pending = new InMemoryPendingInputs();
        $this->notifier = new RecordingNotifier();
        $this->bus = new RecordingBus();
        $events = new EventDispatcher();
        $events->addListener(PendingInputResolved::class, function (PendingInputResolved $e): void {
            $this->resolved[] = $e;
        });
        $this->resume = new ResumePendingInputHandler($this->pending, $this->client, self::tokens(), $this->notifier, $events, $this->harness->clock);
    }

    public function testDeferredCallParksAPendingInputNotifiesAndTellsTheAgentToStop(): void
    {
        $result = $this->deferringCaller()->call(self::call());

        self::assertFalse($result->isError);
        $pending = $this->onlyPending();
        self::assertSame(
            \sprintf('A human has been asked to approve this (request #%s). This item is paused. Do not retry; stop working on it.', $pending->id),
            self::text($result),
        );
        self::assertSame(PendingInputStatus::Pending, $pending->status);
        self::assertSame(['bob', 'doc_approve', self::ARGS, 'document:d1'], [$pending->callerId, $pending->tool, $pending->arguments, $pending->subjectRef]);
        self::assertSame('elicitation/create', $pending->inputRequests['approval']['method'] ?? null);
        self::assertIsString($pending->requestState);
        self::assertSame([$pending->id], $this->notifier->notified);
        self::assertSame('review', $this->document->getStatus());
        self::assertSame(0, $this->harness->subjects->saves);
    }

    public function testAnswerQueuesTheResume(): void
    {
        $pending = $this->defer();

        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['note' => 'ship it on Monday'])]);

        self::assertSame(PendingInputStatus::Answered, $pending->status);
        self::assertSame(['approval' => ['action' => 'accept', 'content' => ['note' => 'ship it on Monday']]], json_decode(json_encode($pending->responses, \JSON_THROW_ON_ERROR), true));
        self::assertEquals([new ResumePendingInput($pending->id)], $this->bus->messages);
        self::assertSame('review', $this->document->getStatus(), 'nothing applied before the resume runs');
    }

    public function testResumeRetriesWithTheOriginalCallersTokenAppliesOnceAndAnnounces(): void
    {
        $pending = $this->defer();
        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['note' => 'ship it on Monday'])]);

        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame(['token-bob', 'token-bob'], $this->http->tokens);
        self::assertSame('published', $this->document->getStatus());
        self::assertEquals(new ApproveInput('ship it on Monday'), $this->document->lastInput);
        self::assertSame(PendingInputStatus::Resolved, $pending->status);
        self::assertSame('published', StatelessClient::at($pending->result, 'structuredContent', 'state'));
        self::assertEquals([new PendingInputResolved($pending->id, 'document:d1', 'doc_approve', false)], $this->resolved);
    }

    public function testDuplicateResumeMessageDoesNothing(): void
    {
        $pending = $this->defer();
        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['note' => 'ship it'])]);

        ($this->resume)(new ResumePendingInput($pending->id));
        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame(1, $this->harness->subjects->saves);
        self::assertCount(1, $this->resolved);
        self::assertCount(2, $this->http->tokens, 'no second retry was sent');
    }

    public function testDeclineResolvesWithoutChangingTheSubject(): void
    {
        $pending = $this->defer();
        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Decline)]);

        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame('review', $this->document->getStatus());
        self::assertSame(PendingInputStatus::Resolved, $pending->status);
        self::assertSame('Not approved: a human declined "doc_approve"; nothing changed.', StatelessClient::at($pending->result, 'structuredContent', 'message'));
        self::assertCount(1, $this->resolved);
    }

    public function testAnswerUnderAnotherKeyCountsAsNoAnswer(): void
    {
        $pending = $this->defer();
        $this->answers()->answer($pending->id, ['other' => new ElicitResult(ElicitAction::Accept, ['note' => 'x'])]);

        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame(PendingInputStatus::Resolved, $pending->status);
        self::assertStringStartsWith('No answer:', self::string(StatelessClient::at($pending->result, 'structuredContent', 'message')));
        self::assertSame('review', $this->document->getStatus());
    }

    public function testExpiredStateResolvesAsAnErrorAndChangesNothing(): void
    {
        $pending = $this->defer();
        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['note' => 'ship it'])]);
        $this->harness->clock->sleep(86400);

        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame(PendingInputStatus::Resolved, $pending->status);
        self::assertTrue($pending->result['isError'] ?? null);
        self::assertSame(RejectionReason::Expired->message(), StatelessClient::at($pending->result, 'content', 0, 'text'));
        self::assertEquals([new PendingInputResolved($pending->id, 'document:d1', 'doc_approve', true)], $this->resolved);
        self::assertSame('review', $this->document->getStatus());
    }

    public function testStateRefusedByTheSdkMarksTheItemFailed(): void
    {
        $pending = $this->defer();
        $pending->askAgain(new InputRequired($pending->inputRequests, 'not-a-signed-state'));
        $this->answers()->answer($pending->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['note' => 'ship it'])]);

        ($this->resume)(new ResumePendingInput($pending->id));

        self::assertSame(PendingInputStatus::Failed, $pending->status);
        self::assertStringStartsWith('MCP error -32602:', self::string(StatelessClient::at($pending->result, 'error')));
        self::assertTrue($this->resolved[0]->failed ?? null);
        self::assertSame('review', $this->document->getStatus());
    }

    public function testScriptedApproverAnswersInlineWithoutAPendingItem(): void
    {
        $approver = new class implements InputRequestHandlerInterface {
            public function handle(InputRequired $request, ToolCall $call): InputOutcome
            {
                return InputOutcome::answered(['approval' => ['action' => 'accept', 'content' => ['note' => 'scripted']]]);
            }
        };

        $result = new HumanInputToolCaller($this->client, $approver, self::tokens())->call(self::call());

        self::assertFalse($result->isError);
        self::assertSame('published', StatelessClient::at($result->structuredContent, 'state'));
        self::assertEquals(new ApproveInput('scripted'), $this->document->lastInput);
        self::assertSame([], $this->pending->withStatus(PendingInputStatus::Pending));
    }

    public function testCallWithoutApprovalReturnsTheResultDirectly(): void
    {
        $result = $this->deferringCaller()->call(new ToolCall('http://inline', 'document:d1', 'bob', 'doc_reject'));

        self::assertFalse($result->isError, self::text($result));
        self::assertSame('draft', StatelessClient::at($result->structuredContent, 'state'));
        self::assertSame([], $this->notifier->notified);
    }

    private function defer(): PendingInput
    {
        $this->deferringCaller()->call(self::call());

        return $this->onlyPending();
    }

    private function onlyPending(): PendingInput
    {
        $all = $this->pending->withStatus(PendingInputStatus::Pending);
        self::assertCount(1, $all);

        return $all[0];
    }

    private function deferringCaller(): HumanInputToolCaller
    {
        return new HumanInputToolCaller($this->client, new DeferringInputRequestHandler($this->pending, $this->notifier, $this->harness->clock), self::tokens());
    }

    private function answers(): PendingInputAnswers
    {
        return new PendingInputAnswers($this->pending, $this->bus, $this->harness->clock);
    }

    private static function call(): ToolCall
    {
        return new ToolCall('http://inline', 'document:d1', 'bob', 'doc_approve', self::ARGS);
    }

    private static function tokens(): CallerTokens
    {
        return new class implements CallerTokens {
            public function tokenFor(string $callerId): string
            {
                return DeferredInputFlowTest::TOKENS[$callerId] ?? throw new \OutOfBoundsException($callerId);
            }
        };
    }

    private static function text(CallToolResult $result): string
    {
        $content = $result->content[0] ?? null;

        return $content instanceof TextContent ? self::string($content->text) : '';
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
