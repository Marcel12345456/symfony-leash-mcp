<?php

declare(strict_types=1);

namespace Leash\HumanInput\Idempotency;

/** Applies an answered input request at most once per nonce and replays the stored result afterwards. */
interface ProcessedInputRequests
{
    /** @return array<string, mixed>|null the stored result of an already processed nonce */
    public function find(string $nonce): ?array;

    /**
     * @param callable(): array<string, mixed> $apply
     */
    public function once(string $nonce, callable $apply): ProcessedResult;
}
