<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/** Bearer token → agent user (PLAN §3.2 Authentication). Tokens live in `.env.local`; an empty token never matches. */
final readonly class StaticTokenHandler implements AccessTokenHandlerInterface
{
    /** @var array<string, string> user identifier => token */
    private array $tokens;

    public function __construct(
        #[Autowire('%env(LEASH_INVESTIGATOR_TOKEN)%')]
        string $investigatorToken,
        #[Autowire('%env(LEASH_RESOLVER_TOKEN)%')]
        string $resolverToken,
    ) {
        $this->tokens = ['agent:investigator' => $investigatorToken, 'agent:resolver' => $resolverToken];
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
}
