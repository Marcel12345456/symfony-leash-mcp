<?php

declare(strict_types=1);

namespace App\Agent\EventListener;

use App\Agent\Message\RunNextActor;
use Leash\HumanInput\Event\PendingInputResolved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** A human answered and the call finished: the workflow decides who works next (G3). */
#[AsEventListener]
final readonly class RunNextActorOnResolved
{
    public function __construct(private MessageBusInterface $bus) {}

    public function __invoke(PendingInputResolved $event): void
    {
        // Subject refs are "<workflow>:<subjectId>" (as WorkflowServerFactory builds them).
        [$workflow, $subjectId] = explode(':', $event->subjectRef, 2) + [1 => ''];
        if ('' === $workflow || '' === $subjectId) {
            throw new \UnexpectedValueException(\sprintf('Subject ref "%s" is not "<workflow>:<subjectId>".', $event->subjectRef));
        }

        $this->bus->dispatch(new RunNextActor($workflow, $subjectId));
    }
}
