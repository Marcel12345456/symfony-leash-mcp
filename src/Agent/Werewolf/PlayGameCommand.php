<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\ModelChoiceFactory;
use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Plays one Werewolf game to the end in this process (PLAN §3.4): a fresh game from the seed, then runner steps
 * until there is a winner. The model comes from `LEASH_MODEL` unless `--model` overrides it. Replay plays the
 * cassette `data/werewolf/seed-<seed>.json`; `--record` (ollama only) writes it (decisions/033).
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
        #[Autowire('%leash.werewolf.cassettes%')]
        private string $cassettes,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Seed for roles and order')]
        int $seed = 7,
        #[Option(description: 'Game id (default: seed-<seed>); an existing game with this id is replaced')]
        ?string $game = null,
        #[Option(description: 'replay or ollama (default: LEASH_MODEL)')]
        ?string $model = null,
        #[Option(description: 'Stop after this many steps; 0 sets the game up at night 1 and stops (e.g. for the MCP Inspector)', name: 'max-steps')]
        int $maxSteps = 60,
        #[Option(description: 'Record the live game into the cassette (ollama only)')]
        bool $record = false,
        #[Option(description: 'Cassette path (default: data/werewolf/seed-<seed>.json)')]
        ?string $cassette = null,
        #[Option(description: 'Live only: this villager seat plays the scripted demo moves (PLAN §3.5); replay takes it from the cassette', name: 'scripted-seat')]
        ?int $scriptedSeat = null,
        #[Option(description: 'This seat is played by a human through the inbox (decisions/035); the game pauses at its turns and the worker resumes it', name: 'human-seat')]
        ?int $humanSeat = null,
    ): int {
        $gameId = $game ?? 'seed-' . $seed;
        $cassette ??= \sprintf('%s/seed-%d.json', $this->cassettes, $seed);
        $live = 'ollama' === ($model ?? $this->models->defaultModel());
        if ($record && !$live) {
            $io->error('--record needs the live model: --model=ollama (or LEASH_MODEL=ollama).');

            return Command::INVALID;
        }
        $start = Game::start($seed, self::NAMES);
        if (null !== $scriptedSeat && !$live) {
            $io->error('--scripted-seat is for live games; a replay takes the scripted seat from its cassette.');

            return Command::INVALID;
        }
        if (null !== $scriptedSeat && Role::Villager !== ($start->seats()[$scriptedSeat] ?? null)?->role) {
            $io->error(\sprintf('--scripted-seat=%d is not a villager with seed %d.', $scriptedSeat, $seed));

            return Command::INVALID;
        }
        if (null !== $humanSeat && (null === ($start->seats()[$humanSeat] ?? null) || $humanSeat === $scriptedSeat)) {
            $io->error(\sprintf('--human-seat=%d is not a seat of this game, or it is the scripted seat.', $humanSeat));

            return Command::INVALID;
        }
        $choice = $this->models->create($model, $live && !$record ? null : $cassette, $scriptedSeat);
        $this->reset($gameId, $start, $humanSeat);
        $io->writeln(\sprintf('Game <info>%s</info> (seed %d, model %s%s), transcript %s', $gameId, $seed, $choice->model, null === $choice->scripted ? '' : ', scripted seat ' . $choice->scripted->seat, $this->transcript->path($gameId)));
        if (0 === $maxSteps) {
            $io->success(\sprintf('Game %s is set up at night 1; nobody has acted. MCP endpoint: /mcp/werewolf_game/%s', $gameId, $gameId));

            return Command::SUCCESS;
        }

        for ($n = 1; $n <= $maxSteps; ++$n) {
            $step = $this->runner->step($gameId, $choice);
            $io->writeln(\sprintf('Step %d: %s, %d turn(s)%s', $n, $step->outcome->value, \count($step->turns), $step->expired ? ', deadline applied' : ''));
            if ($step->gameOver) {
                $winner = $this->entityManager->find(WerewolfGame::class, $gameId)?->game()->winner();
                $left = $choice->replay?->remaining() ?? 0;
                if (0 !== $left) {
                    $io->error(\sprintf('The game ended with %d recorded model answers unused; it diverged from the cassette %s.', $left, $cassette));

                    return Command::FAILURE;
                }
                $io->success('Result: ' . ($winner->value ?? 'unknown') . ($record ? ', recorded to ' . $cassette : ''));

                return Command::SUCCESS;
            }
            if (StepOutcome::Paused === $step->outcome) {
                $io->note(\sprintf('Seat %d waits for a human: answer in the inbox; the messenger worker (make demo) resumes the game.', $humanSeat ?? 0));

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

    private function reset(string $gameId, Game $game, ?int $humanSeat): void
    {
        $existing = $this->entityManager->find(WerewolfGame::class, $gameId);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }
        @unlink($this->transcript->path($gameId));
        $entity = new WerewolfGame($gameId, $game);
        $entity->setHumanSeat($humanSeat);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
