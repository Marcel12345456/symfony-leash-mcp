<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Audit trail of a case: who did what, when. */
#[ORM\Entity]
#[ORM\Index(fields: ['case', 'createdAt'])]
class CaseEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** @param array<string, mixed> $payload */
    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private RefundCase $case,
        #[ORM\Column(length: 32, enumType: CaseEventType::class)]
        private CaseEventType $type,
        #[ORM\Column(length: 64)]
        private string $actor,
        #[ORM\Column(type: 'json')]
        private array $payload,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCase(): RefundCase
    {
        return $this->case;
    }

    public function getType(): CaseEventType
    {
        return $this->type;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
