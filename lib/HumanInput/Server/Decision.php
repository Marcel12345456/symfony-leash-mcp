<?php

declare(strict_types=1);

namespace Leash\HumanInput\Server;

use Mcp\Schema\Result\InputRequiredResult;

final readonly class Decision
{
    private function __construct(
        public Verdict $verdict,
        public ?object $input = null,
        public ?string $nonce = null,
        public ?InputRequiredResult $inputRequired = null,
        /** Why a human declined, when the policy can tell (DecliningApprovalPolicyInterface). */
        public ?string $reason = null,
    ) {}

    public static function proceed(object $input, ?string $nonce): self
    {
        return new self(Verdict::Proceed, $input, $nonce);
    }

    public static function inputRequired(InputRequiredResult $result): self
    {
        return new self(Verdict::InputRequired, inputRequired: $result);
    }

    /** With a reason, the nonce lets the caller handle the decline once per request state. */
    public static function declined(?string $reason = null, ?string $nonce = null): self
    {
        return new self(Verdict::Declined, nonce: $nonce, reason: $reason);
    }

    public static function unanswered(): self
    {
        return new self(Verdict::Unanswered);
    }
}
