<?php

declare(strict_types=1);

namespace Leash\HumanInput\Idempotency;

final readonly class ProcessedResult
{
    /** @param array<string, mixed> $result */
    public function __construct(
        public array $result,
        public bool $replayed,
    ) {}
}
