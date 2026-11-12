<?php

declare(strict_types=1);

namespace App\DocReview\Security;

/** The Doc Review agent callers; their bearer tokens are derived from the kernel secret, so no extra setup. */
final class DocReviewCallers
{
    public const string AUTHOR = 'agent:author';
    public const string REVIEWER = 'agent:reviewer';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::AUTHOR, self::REVIEWER];
    }

    public static function token(#[\SensitiveParameter] string $secret, string $callerId): string
    {
        return '' === $secret ? '' : hash_hmac('sha256', 'leash-docreview:' . $callerId, $secret);
    }
}
