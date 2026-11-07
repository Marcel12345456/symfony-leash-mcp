<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchPolicyInput
{
    /** @param string $query Keywords, e.g. "damaged item refund window" */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public string $query,
    ) {}
}
