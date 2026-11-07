<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

/** Tells a human that a pending input waits for them (inbox, email, Telegram; PLAN §3.3). */
interface HumanInputNotifier
{
    public function notify(PendingInput $pending): void;
}
