<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

/** How one runner step ended. Only Played and Paused (turns before the pause) change the game. */
enum StepOutcome: string
{
    case Played = 'played';
    /** Another run holds the subject lock (PLAN §3.4). */
    case Locked = 'locked';
    /** An open PendingInput pauses the subject; only ResumePendingInput wakes it. Turns played before the pause are kept. */
    case Paused = 'paused';
    case Over = 'over';
    case Missing = 'missing';
}
