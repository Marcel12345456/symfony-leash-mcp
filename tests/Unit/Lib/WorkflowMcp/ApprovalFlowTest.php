<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ApproveInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\StatelessClient;
use Leash\HumanInput\RequestState\RejectionReason;
use Leash\WorkflowMcp\Event\ToolCalled;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/** MRTR on the synthetic workflow: `doc_approve` needs a human (`mcp_approval: doc_approval`). */
final class ApprovalFlowTest extends TestCase
{
    private const array ARGS = ['note' => 'ship it'];

    private Harness $harness;
    private Document $document;

    protected function setUp(): void
    {
        $this->harness = new Harness();
        $this->document = new Document('review');
    }

    public function testFirstCallAsksWithoutSideEffects(): void
    {
        $result = $this->client('bob')->call('doc_approve', self::ARGS);

        self::assertSame('input_required', $result['resultType'] ?? null);
        self::assertSame('elicitation/create', StatelessClient::at($result, 'inputRequests', 'approval', 'method'));
        self::assertSame('Publish with note "ship it"?', StatelessClient::at($result, 'inputRequests', 'approval', 'params', 'message'));
        self::assertIsString($result['requestState'] ?? null);
        self::assertSame('review', $this->document->getStatus());
        self::assertSame(0, $this->harness->subjects->saves);
        self::assertNull($this->harness->processed->find('anything'));
        self::assertSame([], $this->harness->eventsOf(ToolCalled::class));
    }

    public function testAcceptedRetryAppliesTheAmendedInputOnce(): void
    {
        $state = $this->ask();

        $result = $this->retry('bob', $state, ['action' => 'accept', 'content' => ['note' => 'ship it on Monday']]);

        self::assertSame(['state' => 'published', 'availableTools' => ['doc_archive'], 'message' => 'Applied "doc_approve".'], $result['structuredContent'] ?? null);
        self::assertSame('published', $this->document->getStatus());
        self::assertEquals(new ApproveInput('ship it on Monday'), $this->document->lastInput);
        self::assertSame(1, $this->harness->subjects->saves);
    }

    public function testDuplicateRetryReplaysTheStoredResult(): void
    {
        $state = $this->ask();
        $answer = ['action' => 'accept', 'content' => ['note' => 'ship it']];
        $first = $this->retry('bob', $state, $answer);

        $second = $this->retry('bob', $state, $answer);

        self::assertSame($first['structuredContent'] ?? null, $second['structuredContent'] ?? null);
        self::assertFalse($second['isError'] ?? false);
        self::assertSame(1, $this->harness->subjects->saves, 'applied once');
        self::assertCount(1, $this->harness->eventsOf(ToolCalled::class));
    }

    public function testDeclineChangesNothing(): void
    {
        $result = $this->retry('bob', $this->ask(), ['action' => 'decline']);

        self::assertFalse($result['isError'] ?? false);
        self::assertSame('Not approved: a human declined "doc_approve"; nothing changed.', StatelessClient::at($result, 'structuredContent', 'message'));
        self::assertSame(['doc_approve', 'doc_reject', 'doc_read_comments', 'doc_add_comment'], StatelessClient::at($result, 'structuredContent', 'availableTools'));
        self::assertSame('review', $this->document->getStatus());
        self::assertSame(0, $this->harness->subjects->saves);
    }

    public function testCancelChangesNothing(): void
    {
        $result = $this->retry('bob', $this->ask(), ['action' => 'cancel']);

        $message = StatelessClient::at($result, 'structuredContent', 'message');
        self::assertIsString($message);
        self::assertStringStartsWith('No answer:', $message);
        self::assertSame('review', $this->document->getStatus());
        self::assertSame(0, $this->harness->subjects->saves);
    }

    public function testRetryByAnotherCallerIsRejected(): void
    {
        $result = $this->retry('carol', $this->ask(), ['action' => 'accept', 'content' => []]);

        self::assertTrue($result['isError'] ?? null);
        self::assertSame(RejectionReason::WrongCaller->message(), StatelessClient::text($result));
        self::assertSame('review', $this->document->getStatus());
    }

    public function testRetryWithChangedArgumentsIsRejected(): void
    {
        $result = $this->retry('bob', $this->ask(), ['action' => 'accept', 'content' => []], ['note' => 'something else']);

        self::assertTrue($result['isError'] ?? null);
        self::assertSame(RejectionReason::ArgumentsMismatch->message(), StatelessClient::text($result));
        self::assertSame('review', $this->document->getStatus());
    }

    public function testRetryOnAnotherSubjectIsRejected(): void
    {
        $state = $this->ask();
        $other = new Harness();
        $response = $other->client(DocumentWorkflow::create(approvalOnApprove: true), new Document('review'), self::user('bob'), 'd2')
            ->request('tools/call', self::retryParams($state, ['action' => 'accept', 'content' => []], self::ARGS));

        self::assertSame(RejectionReason::WrongTarget->message(), StatelessClient::at($response, 'result', 'content', 0, 'text'));
    }

    public function testExpiredStateIsRejected(): void
    {
        $state = $this->ask();
        $this->harness->clock->sleep(86400);

        $result = $this->retry('bob', $state, ['action' => 'accept', 'content' => []]);

        self::assertSame(RejectionReason::Expired->message(), StatelessClient::text($result));
        self::assertSame('review', $this->document->getStatus());
    }

    public function testTamperedStateIsAnInvalidParamsErrorFromTheSdk(): void
    {
        $state = $this->ask();
        $tampered = substr($state, 0, -2) . ('AA' === substr($state, -2) ? 'BB' : 'AA');

        $response = $this->client('bob')->request('tools/call', self::retryParams($tampered, ['action' => 'accept', 'content' => []], self::ARGS));

        self::assertSame(-32602, StatelessClient::at($response, 'error', 'code'));
        self::assertSame('review', $this->document->getStatus());
    }

    private function ask(): string
    {
        $state = $this->client('bob')->call('doc_approve', self::ARGS)['requestState'] ?? null;
        self::assertIsString($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $answer
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function retry(string $caller, string $state, array $answer, array $arguments = self::ARGS): array
    {
        $response = $this->client($caller)->request('tools/call', self::retryParams($state, $answer, $arguments));
        $result = $response['result'] ?? null;
        self::assertIsArray($result, 'Expected a result, got ' . json_encode($response));

        return array_combine(array_map(strval(...), array_keys($result)), $result);
    }

    /**
     * @param array<string, mixed> $answer
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function retryParams(string $state, array $answer, array $arguments): array
    {
        return ['name' => 'doc_approve', 'arguments' => (object) $arguments, 'inputResponses' => ['approval' => $answer], 'requestState' => $state];
    }

    private function client(string $caller): StatelessClient
    {
        return $this->harness->client(DocumentWorkflow::create(approvalOnApprove: true), $this->document, self::user($caller));
    }

    private static function user(string $name): InMemoryUser
    {
        return new InMemoryUser($name, null, ['ROLE_EDITOR']);
    }
}
