<?php

declare(strict_types=1);

namespace Leash\HumanInput\Idempotency;

/** Process-local store for tests and in-process demos. */
final class InMemoryProcessedInputRequests implements ProcessedInputRequests
{
    /** @var array<string, array<string, mixed>> */
    private array $results = [];

    public function find(string $nonce): ?array
    {
        return $this->results[$nonce] ?? null;
    }

    public function once(string $nonce, callable $apply): ProcessedResult
    {
        if (\array_key_exists($nonce, $this->results)) {
            return new ProcessedResult($this->results[$nonce], true);
        }

        return new ProcessedResult($this->results[$nonce] = $apply(), false);
    }
}
