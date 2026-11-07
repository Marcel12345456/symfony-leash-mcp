<?php

declare(strict_types=1);

namespace App\RefundDesk\Payment;

use App\RefundDesk\Entity\Refund;
use App\RefundDesk\Entity\RefundCase;
use Psr\Clock\ClockInterface;

/** Safety rule: no real money moves. "Paying" a refund only creates the Refund record. */
final readonly class FakePaymentProvider
{
    public function __construct(private ClockInterface $clock) {}

    public function refund(RefundCase $case, int $amountCents, int $proposedAmountCents): Refund
    {
        return new Refund($case, $amountCents, $proposedAmountCents, 'fake-' . bin2hex(random_bytes(8)), $this->clock->now());
    }
}
