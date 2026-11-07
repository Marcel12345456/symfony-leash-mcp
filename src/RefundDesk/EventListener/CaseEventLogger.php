<?php

declare(strict_types=1);

namespace App\RefundDesk\EventListener;

use App\RefundDesk\Entity\CaseEvent;
use App\RefundDesk\Entity\CaseEventType;
use App\RefundDesk\Entity\RefundCase;
use Doctrine\ORM\EntityManagerInterface;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/** Writes the case audit trail from library events and workflow transitions. */
final readonly class CaseEventLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private Security $security,
    ) {}

    #[AsEventListener]
    public function onToolCalled(ToolCalled $event): void
    {
        if ($event->subject instanceof RefundCase) {
            $this->log($event->subject, CaseEventType::ToolCalled, $event->caller->getUserIdentifier(), ['tool' => $event->tool, 'arguments' => $event->arguments, 'isError' => $event->isError]);
        }
    }

    #[AsEventListener]
    public function onCapabilityDenied(CapabilityDenied $event): void
    {
        if ($event->subject instanceof RefundCase) {
            $this->log($event->subject, CaseEventType::CapabilityDenied, $event->caller->getUserIdentifier(), ['tool' => $event->tool, 'state' => $event->places, 'available' => $event->available]);
        }
    }

    /** @param CompletedEvent<object> $event */
    #[AsCompletedListener(workflow: 'refund_case')]
    public function onTransition(CompletedEvent $event): void
    {
        $case = $event->getSubject();
        if ($case instanceof RefundCase) {
            $transition = $event->getTransition();
            $this->log($case, CaseEventType::TransitionApplied, $this->security->getUser()?->getUserIdentifier() ?? 'system', [
                'transition' => $transition?->getName(),
                'from' => $transition?->getFroms(),
                'to' => $transition?->getTos(),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function log(RefundCase $case, CaseEventType $type, string $actor, array $payload): void
    {
        if (!$this->entityManager->contains($case)) {
            return; // not a stored case (e.g. a dry run on an in-memory subject): nothing to attach the event to
        }
        $this->entityManager->persist(new CaseEvent($case, $type, $actor, $payload, $this->clock->now()));
        $this->entityManager->flush();
    }
}
