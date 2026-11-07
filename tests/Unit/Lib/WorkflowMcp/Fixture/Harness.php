<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use App\Tests\Unit\Lib\WorkflowMcp\InputMapperTest;
use Leash\HumanInput\Idempotency\InMemoryProcessedInputRequests;
use Leash\HumanInput\RequestState\RequestBinder;
use Leash\HumanInput\RequestState\RequestStateKey;
use Leash\HumanInput\Server\HumanInputGate;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Leash\WorkflowMcp\Server\WorkflowServerFactory;
use Leash\WorkflowMcp\Tool\PlaceToolHandler;
use Leash\WorkflowMcp\Tool\TransitionToolHandler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/** Wires the library by hand around the synthetic document workflow; records events and saves. */
final class Harness
{
    /** @var list<object> */
    public array $events = [];
    public readonly InMemorySubjectProvider $subjects;
    public readonly InMemoryProcessedInputRequests $processed;
    public readonly MockClock $clock;
    public readonly TransitionToolHandler $transitionTools;
    public readonly WorkflowServerFactory $servers;

    public function __construct()
    {
        $dispatcher = new EventDispatcher();
        foreach ([CapabilityDenied::class, ToolCalled::class] as $event) {
            $dispatcher->addListener($event, function (object $e): void {
                $this->events[] = $e;
            });
        }
        $provider = DocumentWorkflow::provider();
        $this->subjects = new InMemorySubjectProvider();
        $this->processed = new InMemoryProcessedInputRequests();
        $this->clock = new MockClock('2026-11-07 12:00:00');
        $this->transitionTools = new TransitionToolHandler(
            $provider,
            InputMapperTest::mapper(),
            $this->subjects,
            $dispatcher,
            $gate = new HumanInputGate(new RequestBinder($this->clock), $this->processed),
            new ServiceLocator(['doc_approval' => static fn(): ApproveApproval => new ApproveApproval()]),
        );
        $this->servers = new WorkflowServerFactory(
            $provider,
            new SchemaFactory(),
            $this->transitionTools,
            new PlaceToolHandler($provider, InputMapperTest::mapper(), $this->subjects, $dispatcher),
            $dispatcher,
            $gate,
        );
    }

    /** A stateless client for one caller on one subject, signing request states with the test key. */
    public function client(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $subjectId = 'd1'): StatelessClient
    {
        return new StatelessClient($this->servers->create($workflow, $subject, $caller, $subjectId)->setRequestState(self::requestStateKey(), RequestBinder::DEFAULT_TTL));
    }

    public static function requestStateKey(): string
    {
        return RequestStateKey::fromSecret('harness-secret');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function eventsOf(string $class): array
    {
        return array_values(array_filter($this->events, static fn(object $e): bool => $e instanceof $class));
    }
}
