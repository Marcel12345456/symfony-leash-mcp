<?php

declare(strict_types=1);

namespace Leash\HumanInput\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Leash\HumanInput\Idempotency\ProcessedInputRequests;
use Leash\HumanInput\Idempotency\ProcessedResult;
use Psr\Clock\ClockInterface;

/**
 * Applies and records in one transaction: if the apply or the insert fails, neither is kept.
 * A concurrent duplicate fails on the nonce primary key and rolls back its own apply.
 */
final readonly class DoctrineProcessedInputRequests implements ProcessedInputRequests
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {}

    public function find(string $nonce): ?array
    {
        return $this->entityManager->find(ProcessedInputRequest::class, $nonce)?->result;
    }

    public function once(string $nonce, callable $apply): ProcessedResult
    {
        return $this->entityManager->wrapInTransaction(function () use ($nonce, $apply): ProcessedResult {
            $stored = $this->find($nonce);
            if (null !== $stored) {
                return new ProcessedResult($stored, true);
            }

            $result = $apply();
            $this->entityManager->persist(new ProcessedInputRequest($nonce, $result, $this->clock->now()));
            $this->entityManager->flush();

            return new ProcessedResult($result, false);
        });
    }
}
