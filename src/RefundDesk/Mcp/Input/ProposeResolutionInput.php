<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Input;

use App\RefundDesk\Entity\Decision;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ProposeResolutionInput
{
    /**
     * @param Decision $decision    "refund" or "reject"
     * @param int      $amountCents Refund amount in integer cents (e.g. 1200 for €12.00); 0 when rejecting
     * @param string   $rationale   Why, citing the policy and the facts you checked
     */
    public function __construct(
        public Decision $decision,
        #[Assert\PositiveOrZero]
        public int $amountCents,
        #[Assert\NotBlank, Assert\Length(max: 2000)]
        public string $rationale,
    ) {}

    #[Assert\Callback]
    public function validateAmount(ExecutionContextInterface $context): void
    {
        if (Decision::Refund === $this->decision && 0 === $this->amountCents) {
            $context->buildViolation('A refund needs an amount above 0 cents.')->atPath('amountCents')->addViolation();
        }
        if (Decision::Reject === $this->decision && 0 !== $this->amountCents) {
            $context->buildViolation('A rejection must have amountCents 0.')->atPath('amountCents')->addViolation();
        }
    }
}
