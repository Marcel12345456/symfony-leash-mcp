<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput;

use App\Tests\Unit\Lib\HumanInput\Fixture\InlineMcpHttpClient;
use App\Tests\Unit\Lib\HumanInput\Fixture\RecordingBus;
use App\Tests\Unit\Lib\HumanInput\Fixture\RecordingNotifier;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ApproveInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ChooseNoteApproval;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use Leash\HumanInput\Client\CallerTokens;
use Leash\HumanInput\Client\HumanInputToolCaller;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Pending\DeferringInputRequestHandler;
use Leash\HumanInput\Pending\InMemoryPendingInputs;
use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Leash\HumanInput\Pending\ResumePendingInputHandler;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\ElicitResult;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * M2 acceptance: an enum elicitation renders as a choice in the inbox form, and the chosen value
 * resumes the synthetic workflow (deferred → form → answer → retry → applied).
 */
final class EnumAnswerRoundTripTest extends TestCase
{
    public function testChosenEnumValueResumesTheWorkflow(): void
    {
        $harness = new Harness(new ChooseNoteApproval());
        $document = new Document('review');
        $http = new InlineMcpHttpClient(static fn(string $token) => $harness->builder(DocumentWorkflow::create(approvalOnApprove: true), $document, new InMemoryUser('bob', null, ['ROLE_EDITOR'])));
        $factory = new Psr17Factory();
        $client = new RawMcpClient($http, $factory, $factory);
        $pending = new InMemoryPendingInputs();
        $notifier = new RecordingNotifier();
        $tokens = new class implements CallerTokens {
            public function tokenFor(string $callerId): string
            {
                return 'token-' . $callerId;
            }
        };

        new HumanInputToolCaller($client, new DeferringInputRequestHandler($pending, $notifier, $harness->clock), $tokens)
            ->call(new ToolCall('http://inline', 'document:d1', 'bob', 'doc_approve', ['note' => 'tbd']));
        [$item] = $pending->withStatus(PendingInputStatus::Pending);

        // Inbox: the stored ask renders as a choice.
        $params = $item->inputRequests['approval']['params'] ?? null;
        self::assertIsArray($params);
        $schema = $params['requestedSchema'] ?? null;
        self::assertIsArray($schema);
        $form = ElicitationFormTest::form()->create($schema, 'approval');
        $choices = $form->createView()->children['note']->vars['choices'];
        self::assertIsArray($choices);
        $labels = [];
        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $labels[] = $choice->label;
        }
        self::assertSame(['Ship on Monday', 'Ship on Friday'], $labels);

        $form->submit(['note' => 'ship-friday']);
        self::assertTrue($form->isValid());
        $bus = new RecordingBus();
        new PendingInputAnswers($pending, $bus, $harness->clock)
            ->answer($item->id, ['approval' => new ElicitResult(ElicitAction::Accept, ElicitationFormTest::form()->content($schema, $form))]);
        self::assertEquals([new ResumePendingInput($item->id)], $bus->messages);

        new ResumePendingInputHandler($pending, $client, $tokens, $notifier, new EventDispatcher(), $harness->clock)(new ResumePendingInput($item->id));

        self::assertSame(PendingInputStatus::Resolved, $item->status);
        self::assertSame('published', $document->getStatus());
        self::assertEquals(new ApproveInput('ship-friday'), $document->lastInput);
    }
}
