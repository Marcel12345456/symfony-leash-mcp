<?php

declare(strict_types=1);

namespace App\Telegram;

enum TelegramCallbackOutcome: string
{
    /** Another chat or user: no reply at all. */
    case Ignored = 'ignored';
    case Unknown = 'unknown';
    case Answered = 'answered';
    case AlreadyAnswered = 'already_answered';
}
