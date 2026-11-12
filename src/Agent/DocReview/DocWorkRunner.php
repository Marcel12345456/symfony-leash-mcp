<?php

declare(strict_types=1);

namespace App\Agent\DocReview;

use App\Agent\ModelChoice;
use App\DocReview\Entity\Document;
use App\DocReview\Security\DocReviewCallers;
use Doctrine\ORM\EntityManagerInterface;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\WorkflowMcp\Capability\ActorSelector;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Works one document until it waits for a human, is published, or nobody acts (PLAN §3.6): the workflow picks the
 * next agent (G3, ActorSelector), never an LLM. A turn that changes nothing ends the run.
 */
final readonly class DocWorkRunner
{
    public const string WORKFLOW = 'doc_review';
    public const int MAX_TURNS = 12;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('doc_review')]
        private WorkflowInterface $workflow,
        private ActorSelector $actors,
        private DocTurnRunner $turns,
        private PendingInputs $pendingInputs,
    ) {}

    /** @return list<string> one line per turn: "<caller>: <state before> → <state after>" */
    public function work(string $documentId, ModelChoice $model): array
    {
        $log = [];
        for ($turn = 0; $turn < self::MAX_TURNS; ++$turn) {
            $document = $this->load($documentId);
            if (null === $document || $this->paused($documentId)) {
                break;
            }
            $caller = ($this->actors->actorsAmong($this->workflow, $document, self::callers())[0] ?? null)?->getUserIdentifier();
            if (null === $caller) {
                break;
            }
            $before = $document->getStatus();
            $this->turns->play($documentId, $caller, $model);
            $after = $this->load($documentId)?->getStatus();
            $log[] = \sprintf('%s: %s → %s', $caller, $before, $after ?? '?');
            if ($after === $before && !$this->paused($documentId)) {
                break;
            }
        }

        return $log;
    }

    /** @return list<InMemoryUser> */
    private static function callers(): array
    {
        return [
            new InMemoryUser(DocReviewCallers::AUTHOR, null, ['ROLE_AUTHOR']),
            new InMemoryUser(DocReviewCallers::REVIEWER, null, ['ROLE_REVIEWER']),
        ];
    }

    private function load(string $documentId): ?Document
    {
        $this->entityManager->clear();

        return $this->entityManager->find(Document::class, $documentId);
    }

    /** @phpstan-impure reads the pending inputs, which a turn may have changed */
    private function paused(string $documentId): bool
    {
        foreach ([PendingInputStatus::Pending, PendingInputStatus::Answered] as $status) {
            foreach ($this->pendingInputs->withStatus($status) as $pending) {
                if (self::WORKFLOW . ':' . $documentId === $pending->subjectRef) {
                    return true;
                }
            }
        }

        return false;
    }
}
