<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/**
 * One `tools/call` as the caller made it: everything needed to resume it later from another process.
 * The token is not part of it; it is looked up by caller id (`CallerTokens`) when the call is sent.
 */
final readonly class ToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $endpoint,
        public string $subjectRef,
        public string $callerId,
        public string $tool,
        public array $arguments = [],
    ) {}
}
