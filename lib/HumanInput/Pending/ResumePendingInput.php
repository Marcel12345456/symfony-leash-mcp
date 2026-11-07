<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

/** Message: retry the answered call of a pending input. */
final readonly class ResumePendingInput
{
    public function __construct(public string $pendingId) {}
}
