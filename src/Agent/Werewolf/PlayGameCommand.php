<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\ModelChoiceFactory;
use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plays one Werewolf game to the end in this process (PLAN §3.4): a fresh game from the seed, then runner steps
 * until there is a winner. The model comes from `LEASH_MODEL` unless `--model` overrides it.
 */
#[AsCommand('leash:werewolf:play', 'Play one Werewolf game with six AI seats (replay by default)')]
final readonly class PlayGameCommand
{
    /** @var list<string> */
    public const array NAMES = ['Anna', 'Ben', 'Cara', 'Dan', 'Eve', 'Finn'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NextActorRunner $runner,
        private TranscriptWriter $transcript,
        private ModelChoiceFactory $models,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Seed for roles and order')]
        int $seed = 7,
        #[Option(description: 'Game id (default: seed-<seed>); an existing game with this id is replaced')]
        ?string $game = null,
        #[Option(description: 'replay or ollama (default: LEASH_MODEL)')]
        ?string $model = null,
        #[Option(description: 'Stop after this many steps', name: 'max-steps')]
        int $maxSteps = 60,
    ): int {
        $gameId = $game ?? 'seed-' . $seed;
        $choice = $this->models->create($model);
        $this->reset($gameId, Game::start($seed, self::NAMES));
        $io->writeln(\sprintf('Game <info>%s</info> (seed %d, model %s), transcript %s', $gameId, $seed, $choice->model, $this->transcript->path($gameId)));

        for ($n = 1; $n <= $maxSteps; ++$n) {
            $step = $this->runner->step($gameId, $choice);
            $io->writeln(\sprintf('Step %d: %s, %d turn(s)%s', $n, $step->outcome->value, \count($step->turns), $step->expired ? ', deadline applied' : ''));
            if ($step->gameOver) {
                $winner = $this->entityManager->find(WerewolfGame::class, $gameId)?->game()->winner();
                $io->success('Result: ' . ($winner->value ?? 'unknown'));

                return Command::SUCCESS;
            }
            if (StepOutcome::Played !== $step->outcome) {
                break;
            }
        }

        $current = $this->entityManager->find(WerewolfGame::class, $gameId)?->game();
        if (null !== $current) {
            $this->transcript->finish($gameId, $current);
        }
        $io->error(\sprintf('The game did not finish within %d steps.', $maxSteps));

        return Command::FAILURE;
    }

    private function reset(string $gameId, Game $game): void
    {
        $existing = $this->entityManager->find(WerewolfGame::class, $gameId);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }
        @unlink($this->transcript->path($gameId));
        $this->entityManager->persist(new WerewolfGame($gameId, $game));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
