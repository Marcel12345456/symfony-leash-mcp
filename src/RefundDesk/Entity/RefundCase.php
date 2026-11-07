<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Subject of the `refund_case` state machine (method marking store, decisions/012). */
#[ORM\Entity]
class RefundCase
{
    #[ORM\Column(length: 32)]
    private string $status = 'received';

    #[ORM\Column(nullable: true, enumType: Decision::class)]
    private ?Decision $decision = null;

    #[ORM\Column(nullable: true)]
    private ?int $proposedAmountCents = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rationale = null;

    /** Reply to the customer when the case is rejected. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerReply = null;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 16)]
        private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
        private Customer $customer,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
        private ?Order $order,
        #[ORM\Column(type: 'text')]
        private string $customerMessage,
        #[ORM\Column]
        private \DateTimeImmutable $openedAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getCustomerMessage(): string
    {
        return $this->customerMessage;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param array<string, mixed> $context */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = $status;
    }

    public function getDecision(): ?Decision
    {
        return $this->decision;
    }

    public function getProposedAmountCents(): ?int
    {
        return $this->proposedAmountCents;
    }

    public function getRationale(): ?string
    {
        return $this->rationale;
    }

    public function getCustomerReply(): ?string
    {
        return $this->customerReply;
    }

    /** Guards use these: ExpressionLanguage cannot compare an enum with a string (PLAN §3.5). */
    public function isRefundDecision(): bool
    {
        return Decision::Refund === $this->decision;
    }

    public function isRejectDecision(): bool
    {
        return Decision::Reject === $this->decision;
    }

    public function propose(Decision $decision, int $amountCents, string $rationale): void
    {
        $this->decision = $decision;
        $this->proposedAmountCents = Decision::Refund === $decision ? $amountCents : null;
        $this->rationale = $rationale;
    }

    public function reject(string $customerReply): void
    {
        $this->customerReply = $customerReply;
    }

    /** Seed only: back to a fresh case with new content (idempotent upsert). */
    public function reset(Customer $customer, ?Order $order, string $customerMessage, \DateTimeImmutable $openedAt): void
    {
        $this->customer = $customer;
        $this->order = $order;
        $this->customerMessage = $customerMessage;
        $this->openedAt = $openedAt;
        $this->status = 'received';
        $this->decision = null;
        $this->proposedAmountCents = null;
        $this->rationale = null;
        $this->customerReply = null;
    }
}
