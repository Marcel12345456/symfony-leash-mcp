<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/** What an input request handler did: answered now (tests, scripted approver) or deferred to a human. */
final readonly class InputOutcome
{
    /** @param array<string, array<string, mixed>>|null $responses */
    private function __construct(
        public ?array $responses,
        public ?string $pendingId,
    ) {}

    /** @param array<string, array<string, mixed>> $responses key => `ElicitResult` wire shape */
    public static function answered(array $responses): self
    {
        return new self($responses, null);
    }

    public static function deferred(string $pendingId): self
    {
        return new self(null, $pendingId);
    }
}
