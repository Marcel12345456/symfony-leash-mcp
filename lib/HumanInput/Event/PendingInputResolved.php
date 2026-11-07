<?php

declare(strict_types=1);

namespace Leash\HumanInput\Event;

/** A pending input's call was resumed and finished (resolved or failed); the workflow can move on. */
final readonly class PendingInputResolved
{
    public function __construct(
        public string $pendingId,
        public string $subjectRef,
        public string $tool,
        public bool $failed,
    ) {}
}
