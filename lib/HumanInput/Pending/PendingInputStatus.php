<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

enum PendingInputStatus: string
{
    /** Waiting for a human. */
    case Pending = 'pending';
    /** A human answered; the resume is queued. */
    case Answered = 'answered';
    /** The resumed call returned a final result. */
    case Resolved = 'resolved';
    /** The resumed call was refused (e.g. the request state expired). */
    case Failed = 'failed';
}
