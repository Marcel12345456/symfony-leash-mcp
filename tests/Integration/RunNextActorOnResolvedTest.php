<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Agent\Message\RunNextActor;
use Leash\HumanInput\Event\PendingInputResolved;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** M2 done-signal: a resolved pending input queues `RunNextActor` for its subject (handler arrives in M3). */
final class RunNextActorOnResolvedTest extends KernelTestCase
{
    public function testResolvedPendingInputQueuesRunNextActor(): void
    {
        $events = self::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $events);

        $events->dispatch(new PendingInputResolved('abc123', 'refund_case:C2', 'refund_issue', false));

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertEquals([new RunNextActor('refund_case', 'C2')], array_map(static fn($e): object => $e->getMessage(), $transport->getSent()));
    }

    public function testMalformedSubjectRefIsRefused(): void
    {
        $events = self::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $events);

        $this->expectException(\UnexpectedValueException::class);

        $events->dispatch(new PendingInputResolved('abc123', 'no-workflow-part', 'refund_issue', false));
    }
}
