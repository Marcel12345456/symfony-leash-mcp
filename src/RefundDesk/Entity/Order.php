<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    public function __construct(
        #[ORM\Id, ORM\Column(length: 16)]
        private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
        private Customer $customer,
        #[ORM\Column(length: 200)]
        private string $item,
        #[ORM\Column]
        private int $amountCents,
        #[ORM\Column]
        private \DateTimeImmutable $deliveredAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getItem(): string
    {
        return $this->item;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getDeliveredAt(): \DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function update(Customer $customer, string $item, int $amountCents, \DateTimeImmutable $deliveredAt): void
    {
        $this->customer = $customer;
        $this->item = $item;
        $this->amountCents = $amountCents;
        $this->deliveredAt = $deliveredAt;
    }
}
