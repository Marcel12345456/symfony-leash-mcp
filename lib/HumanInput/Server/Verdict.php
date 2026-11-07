<?php

declare(strict_types=1);

namespace Leash\HumanInput\Server;

enum Verdict
{
    /** Apply the (possibly amended) input. */
    case Proceed;
    /** Return `input_required`; nothing may have changed. */
    case InputRequired;
    /** The human said no. */
    case Declined;
    /** The human cancelled, or the answer was missing or empty. */
    case Unanswered;
}
