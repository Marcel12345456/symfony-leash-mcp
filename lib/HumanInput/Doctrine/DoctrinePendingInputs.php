<?php

declare(strict_types=1);

namespace Leash\HumanInput\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;

final readonly class DoctrinePendingInputs implements PendingInputs
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function get(string $id): ?PendingInput
    {
        return $this->entityManager->find(PendingInput::class, $id);
    }

    public function save(PendingInput $pending): void
    {
        $this->entityManager->persist($pending);
        $this->entityManager->flush();
    }

    public function withStatus(PendingInputStatus $status): array
    {
        return $this->entityManager->getRepository(PendingInput::class)->findBy(['status' => $status], ['createdAt' => 'ASC']);
    }
}
