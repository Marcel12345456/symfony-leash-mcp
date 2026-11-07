<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

enum Decision: string
{
    case Refund = 'refund';
    case Reject = 'reject';
}
