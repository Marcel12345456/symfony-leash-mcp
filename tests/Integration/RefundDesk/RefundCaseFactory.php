<?php

declare(strict_types=1);

namespace App\Tests\Integration\RefundDesk;

use App\RefundDesk\Entity\Customer;
use App\RefundDesk\Entity\Decision;
use App\RefundDesk\Entity\Order;
use App\RefundDesk\Entity\RefundCase;

/** Unpersisted refund cases in any state, for visibility and voter tests. */
final class RefundCaseFactory
{
    public static function create(string $status = 'received', ?Decision $decision = null, int $proposedCents = 0, int $orderCents = 1200): RefundCase
    {
        $customer = new Customer('CU-T', 'Test Customer', 'test@example.com');
        $case = new RefundCase('C-T', $customer, new Order('O-T', $customer, 'Test item', $orderCents, new \DateTimeImmutable('-3 days')), 'Broken.', new \DateTimeImmutable());
        if (null !== $decision) {
            $case->propose($decision, $proposedCents, 'Test rationale.');
        }
        $case->setStatus($status);

        return $case;
    }
}
