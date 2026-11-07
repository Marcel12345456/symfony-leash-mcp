<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

/** Derives the SDK request-state signing key (32 bytes) from the application secret. */
final class RequestStateKey
{
    public static function fromSecret(#[\SensitiveParameter] string $secret): string
    {
        if ('' === $secret) {
            throw new \InvalidArgumentException('An empty secret cannot sign request states; set APP_SECRET.');
        }

        return hash_hmac('sha256', 'leash.request_state', $secret, true);
    }
}
