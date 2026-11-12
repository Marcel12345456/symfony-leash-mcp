<?php

declare(strict_types=1);

namespace App\Security;

use App\DocReview\Security\DocReviewCallers;
use App\Werewolf\Security\WerewolfCallers;
use Leash\HumanInput\Client\CallerTokens;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Bearer token → agent user (PLAN §3.2 Authentication). Tokens live in `.env.local`; an empty token never matches.
 * Werewolf seat and moderator tokens are derived from one secret (WerewolfCallers), Doc Review agent tokens from
 * the kernel secret (DocReviewCallers).
 * Also the reverse lookup a resume needs: the original caller's token (decisions/017).
 */
final readonly class StaticTokenHandler implements AccessTokenHandlerInterface, CallerTokens
{
    /** @var array<string, string> user identifier => token */
    private array $tokens;

    public function __construct(
        #[Autowire('%env(LEASH_INVESTIGATOR_TOKEN)%')]
        string $investigatorToken,
        #[Autowire('%env(LEASH_RESOLVER_TOKEN)%')]
        string $resolverToken,
        #[Autowire('%env(LEASH_WEREWOLF_SECRET)%')]
        #[\SensitiveParameter]
        string $werewolfSecret,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        string $kernelSecret,
    ) {
        $tokens = ['agent:investigator' => $investigatorToken, 'agent:resolver' => $resolverToken];
        foreach (WerewolfCallers::all() as $caller) {
            $tokens[$caller] = WerewolfCallers::token($werewolfSecret, $caller);
        }
        foreach (DocReviewCallers::all() as $caller) {
            $tokens[$caller] = DocReviewCallers::token($kernelSecret, $caller);
        }
        $this->tokens = $tokens;
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        foreach ($this->tokens as $user => $token) {
            if ('' !== $token && hash_equals($token, $accessToken)) {
                return new UserBadge($user);
            }
        }

        throw new BadCredentialsException('Invalid access token.');
    }

    public function tokenFor(string $callerId): string
    {
        $token = $this->tokens[$callerId] ?? '';
        if ('' === $token) {
            throw new \OutOfBoundsException(\sprintf('No token configured for caller "%s".', $callerId));
        }

        return $token;
    }
}
