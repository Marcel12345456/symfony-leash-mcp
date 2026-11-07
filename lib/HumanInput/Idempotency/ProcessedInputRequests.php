<?php

declare(strict_types=1);

namespace Leash\HumanInput\Idempotency;

/** Applies an answered input request at most once per nonce and replays the stored result afterwards. */
interface ProcessedInputRequests
{
    /**
     * @param callable(): array<string, mixed> $apply
     */
    public function once(string $nonce, callable $apply): ProcessedResult;
}
