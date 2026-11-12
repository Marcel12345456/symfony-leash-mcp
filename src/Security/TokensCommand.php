<?php

declare(strict_types=1);

namespace App\Security;

use App\DocReview\Security\DocReviewCallers;
use App\Werewolf\Security\WerewolfCallers;
use Leash\HumanInput\Client\CallerTokens;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bearer tokens of the demo callers, for an MCP client such as the Inspector (`make inspector CALLER=seat:1`).
 * Tokens are derived from the secrets in `.env.local`; this only prints them.
 */
#[AsCommand('leash:tokens', 'Print the bearer token of one demo caller, or list all callers with their tokens')]
final readonly class TokensCommand
{
    public function __construct(private CallerTokens $tokens) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Caller id, e.g. seat:1, werewolf:moderator, agent:reviewer; prints only the token')]
        ?string $caller = null,
    ): int {
        if (null !== $caller) {
            try {
                $io->writeln($this->tokens->tokenFor($caller), SymfonyStyle::OUTPUT_RAW);
            } catch (\OutOfBoundsException $e) {
                $io->error($e->getMessage() . ' Known callers: ' . implode(', ', self::callers()) . '. Run `make install` once for the secrets.');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        $io->table(['caller', 'token'], array_map(fn(string $c): array => [$c, $this->tokenOrHint($c)], self::callers()));

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private static function callers(): array
    {
        return [...WerewolfCallers::all(), ...DocReviewCallers::all()];
    }

    private function tokenOrHint(string $caller): string
    {
        try {
            return $this->tokens->tokenFor($caller);
        } catch (\OutOfBoundsException) {
            return '(no secret: run make install)';
        }
    }
}
