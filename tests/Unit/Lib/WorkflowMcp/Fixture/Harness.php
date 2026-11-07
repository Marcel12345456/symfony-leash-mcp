<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use App\Tests\Unit\Lib\WorkflowMcp\InputMapperTest;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Leash\WorkflowMcp\Server\WorkflowServerFactory;
use Leash\WorkflowMcp\Tool\PlaceToolHandler;
use Leash\WorkflowMcp\Tool\TransitionToolHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** Wires the library by hand around the synthetic document workflow; records events and saves. */
final class Harness
{
    /** @var list<object> */
    public array $events = [];
    public readonly InMemorySubjectProvider $subjects;
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
        $this->transitionTools = new TransitionToolHandler($provider, InputMapperTest::mapper(), $this->subjects, $dispatcher);
        $this->servers = new WorkflowServerFactory(
            $provider,
            new SchemaFactory(),
            $this->transitionTools,
            new PlaceToolHandler($provider, InputMapperTest::mapper(), $this->subjects, $dispatcher),
            $dispatcher,
        );
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
