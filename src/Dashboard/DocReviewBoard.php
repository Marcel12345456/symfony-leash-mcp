<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\DocReview\Entity\DocEvent;
use App\DocReview\Entity\DocEventType;
use App\DocReview\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Leash\HumanInput\Pending\PendingInput;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Doc Review tab (PLAN §3.7): every document with its workflow diagram and one timeline of who did what.
 * Timeline kinds drive the colours: denied (red), requested (amber), transition (green), call, declined.
 */
final readonly class DocReviewBoard
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('doc_review')]
        private WorkflowInterface $workflow,
        private WorkflowDiagram $diagram,
    ) {}

    /** @return list<array{id: string, status: string, title: ?string, brief: string, headline: ?string, diagram: string, timeline: list<array{at: string, kind: string, actor: string, text: string}>}> */
    public function view(): array
    {
        $this->entityManager->clear();
        $documents = $this->entityManager->getRepository(Document::class)->findBy([], ['id' => 'ASC']);

        return array_map(fn(Document $d): array => [
            'id' => $d->getId(),
            'status' => $d->getStatus(),
            'title' => $d->title(),
            'brief' => $d->brief(),
            'headline' => $d->headline(),
            'diagram' => $this->diagram->mermaid($this->workflow, $d->getStatus()),
            'timeline' => $this->timeline($d->getId()),
        ], $documents);
    }

    /** @return list<array{at: string, kind: string, actor: string, text: string}> */
    private function timeline(string $documentId): array
    {
        $entries = [];
        foreach ($this->entityManager->getRepository(DocEvent::class)->findBy(['documentId' => $documentId], ['id' => 'ASC']) as $event) {
            $entries[] = self::fromEvent($event);
        }
        foreach ($this->entityManager->getRepository(PendingInput::class)->findBy(['subjectRef' => 'doc_review:' . $documentId]) as $pending) {
            $entries[] = ['at' => $pending->createdAt->format(\DATE_ATOM), 'kind' => 'requested', 'actor' => $pending->callerId, 'text' => \sprintf('%s waits for a human (%s)', $pending->tool, $pending->status->value)];
        }
        usort($entries, static fn(array $a, array $b): int => $a['at'] <=> $b['at']);

        return $entries;
    }

    /** @return array{at: string, kind: string, actor: string, text: string} */
    private static function fromEvent(DocEvent $event): array
    {
        $p = $event->payload;
        [$kind, $text] = match ($event->type) {
            DocEventType::TransitionApplied => ['transition', \sprintf('%s → %s', self::str($p['transition'] ?? null), implode(', ', self::list($p['to'] ?? null)))],
            DocEventType::CapabilityDenied => ['denied', \sprintf('%s denied in %s; visible: [%s]', self::str($p['tool'] ?? null), implode(', ', self::list($p['state'] ?? null)), implode(', ', self::list($p['available'] ?? null)))],
            DocEventType::InputDeclined => ['declined', 'declined' . (\is_string($p['note'] ?? null) ? ': ' . $p['note'] : '')],
            DocEventType::ToolCalled => ['call', self::str($p['tool'] ?? null) . (true === ($p['isError'] ?? false) ? ' (error)' : '')],
        };

        return ['at' => $event->at->format(\DATE_ATOM), 'kind' => $kind, 'actor' => $event->actor, 'text' => $text];
    }

    private static function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '?';
    }

    /** @return list<string> */
    private static function list(mixed $value): array
    {
        return \is_array($value) ? array_values(array_map(self::str(...), $value)) : [];
    }
}
