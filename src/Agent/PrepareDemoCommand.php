<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\DocReview\WorkCommand;
use App\Agent\Werewolf\PlayGameCommand;
use App\DocReview\Command\SeedDemoCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `make demo` (PLAN M7, decisions/038): the whole story with no model. Resets the Doc Review documents, replays the
 * Werewolf demo game (seed 8), then the Doc Review agents up to the editor's sign-off, which waits for a human.
 * `LEASH_REPLAY_PACE` slows the replay down so the dashboard can follow it.
 */
#[AsCommand('leash:demo:prepare', 'Replay the Werewolf demo game and the Doc Review agents (no model by default)')]
final readonly class PrepareDemoCommand
{
    public const int DEMO_SEED = 8;

    public function __construct(
        private SeedDemoCommand $seed,
        private PlayGameCommand $werewolf,
        private WorkCommand $docReview,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'replay or ollama (default: LEASH_MODEL)')]
        ?string $model = null,
    ): int {
        $io->title('Doc Review: fresh documents');
        if (Command::SUCCESS !== ($this->seed)($io, reset: true)) {
            return Command::FAILURE;
        }
        $io->title('Werewolf: seed ' . self::DEMO_SEED);
        if (Command::SUCCESS !== ($this->werewolf)($io, seed: self::DEMO_SEED, model: $model)) {
            return Command::FAILURE;
        }
        $io->title('Doc Review: agents');

        return ($this->docReview)($io, model: $model);
    }
}
