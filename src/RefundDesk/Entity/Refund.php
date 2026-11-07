<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A refund paid through the fake payment provider. No real money moves. */
#[ORM\Entity]
class Refund
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private RefundCase $case,
        #[ORM\Column]
        private int $amountCents,
        #[ORM\Column]
        private int $proposedAmountCents,
        #[ORM\Column(length: 64)]
        private string $providerReference,
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

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getProposedAmountCents(): int
    {
        return $this->proposedAmountCents;
    }

    public function getProviderReference(): string
    {
        return $this->providerReference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
