<?php

declare(strict_types=1);

namespace App\Werewolf\Security;

use App\Werewolf\Rules\Game;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Werewolf callers: `seat:1` … `seat:6` and `werewolf:moderator`. Their bearer tokens are derived from one secret
 * (HMAC per caller id), so `.env.local` holds one value instead of seven.
 */
final class WerewolfCallers
{
    public const string MODERATOR = 'werewolf:moderator';

    /** @return list<string> every caller id */
    public static function all(): array
    {
        return [...array_map(self::seat(...), range(1, Game::SEATS)), self::MODERATOR];
    }

    public static function seat(int $number): string
    {
        return 'seat:' . $number;
    }

    /** The seat number of a seat caller, else null. */
    public static function seatOf(UserInterface $caller): ?int
    {
        return 1 === preg_match('/^seat:([1-9]\d*)$/', $caller->getUserIdentifier(), $m) ? (int) $m[1] : null;
    }

    /** Empty secret → empty token, which never authenticates. */
    public static function token(#[\SensitiveParameter] string $secret, string $callerId): string
    {
        return '' === $secret ? '' : hash_hmac('sha256', 'leash-werewolf:' . $callerId, $secret);
    }
}
