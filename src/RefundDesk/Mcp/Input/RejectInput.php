<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RejectInput
{
    /** @param string $customerReply Message to the customer explaining the rejection and any next step */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 2000)]
        public string $customerReply,
    ) {}
}
