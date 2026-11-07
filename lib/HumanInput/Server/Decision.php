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
    ) {}

    public static function proceed(object $input, ?string $nonce): self
    {
        return new self(Verdict::Proceed, $input, $nonce);
    }

    public static function inputRequired(InputRequiredResult $result): self
    {
        return new self(Verdict::InputRequired, inputRequired: $result);
    }

    public static function declined(): self
    {
        return new self(Verdict::Declined);
    }

    public static function unanswered(): self
    {
        return new self(Verdict::Unanswered);
    }
}
