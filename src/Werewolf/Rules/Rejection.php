<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

/** Why the engine refused an action; the message says what the seat can do instead. */
final readonly class Rejection
{
    public function __construct(
        public RejectionReason $reason,
        public string $message,
    ) {}
}
