<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/**
 * The bearer token of a caller. A resume must use the original caller's token because the
 * request state is bound to the caller; tokens are never stored with a pending input.
 */
interface CallerTokens
{
    /** @throws \OutOfBoundsException for an unknown caller */
    public function tokenFor(string $callerId): string;
}
