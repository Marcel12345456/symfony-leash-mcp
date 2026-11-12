<?php

declare(strict_types=1);

namespace App\DocReview\Workflow;

use App\DocReview\Entity\DocEvent;
use App\DocReview\Entity\DocEventType;
use App\DocReview\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Leash\WorkflowMcp\Event\ApprovalDeclined;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/** Records an editor's decline note on the document (decisions/036) and writes the document's event log. */
final readonly class DocReviewListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private Security $security,
    ) {}

    #[AsEventListener]
    public function onApprovalDeclined(ApprovalDeclined $event): void
    {
        if ($event->subject instanceof Document) {
            $event->subject->noteFromEditor($event->reason);
            $this->log($event->subject, DocEventType::InputDeclined, 'editor', ['tool' => $event->tool, 'note' => $event->reason]);
        }
    }

    #[AsEventListener]
    public function onToolCalled(ToolCalled $event): void
    {
        if ($event->subject instanceof Document) {
            $this->log($event->subject, DocEventType::ToolCalled, $event->caller->getUserIdentifier(), ['tool' => $event->tool, 'isError' => $event->isError]);
        }
    }

    #[AsEventListener]
    public function onCapabilityDenied(CapabilityDenied $event): void
    {
        if ($event->subject instanceof Document) {
            $this->log($event->subject, DocEventType::CapabilityDenied, $event->caller->getUserIdentifier(), ['tool' => $event->tool, 'state' => $event->places, 'available' => $event->available]);
        }
    }

    /** @param CompletedEvent<object> $event */
    #[AsCompletedListener(workflow: 'doc_review')]
    public function onTransition(CompletedEvent $event): void
    {
        $document = $event->getSubject();
        if ($document instanceof Document) {
            $transition = $event->getTransition();
            $this->log($document, DocEventType::TransitionApplied, $this->security->getUser()?->getUserIdentifier() ?? 'system', [
                'transition' => $transition?->getName(), 'from' => $transition?->getFroms(), 'to' => $transition?->getTos(),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function log(Document $document, DocEventType $type, string $actor, array $payload): void
    {
        if ($this->entityManager->contains($document)) {
            $this->entityManager->persist(new DocEvent($document->getId(), $type, $actor, $payload, $this->clock->now()));
            $this->entityManager->flush();
        }
    }
}
