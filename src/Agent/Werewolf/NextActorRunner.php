<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\ModelChoice;
use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Security\WerewolfCallers;
use App\Werewolf\Workflow\GameModerator;
use Doctrine\ORM\EntityManagerInterface;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\WorkflowMcp\Capability\ActorSelector;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * One step of a Werewolf game (PLAN §3.4): under the subject lock, unless a human input pauses the game, the workflow
 * picks the seats that may act now (G3, `ActorSelector::actorsAmong`) and each plays one turn. A seat that ends its
 * turn without acting has missed the deadline: `GameModerator::expire()` skips or abstains it. Then the moderator
 * advances. The game's human seat plays without a model (HumanSeatTurnRunner); while its question is open, the step
 * stops and the game is paused until the answer resumes it.
 */
final readonly class NextActorRunner
{
    public const string WORKFLOW = 'werewolf_game';

    public function __construct(
        private LockFactory $locks,
        private EntityManagerInterface $entityManager,
        #[Target('werewolf_game')]
        private WorkflowInterface $workflow,
        private ActorSelector $actors,
        private GameModerator $moderator,
        private SeatTurnRunner $turns,
        private HumanSeatTurnRunner $humanTurns,
        private TranscriptWriter $transcript,
        private PendingInputs $pendingInputs,
    ) {}

    public static function lockKey(string $gameId): string
    {
        return \sprintf('leash.subject.%s.%s', self::WORKFLOW, $gameId);
    }

    public function step(string $gameId, ModelChoice $model): Step
    {
        $lock = $this->locks->createLock(self::lockKey($gameId));
        if (!$lock->acquire()) {
            return new Step(StepOutcome::Locked);
        }

        try {
            if ($this->paused($gameId)) {
                return new Step(StepOutcome::Paused);
            }
            $game = $this->load($gameId);
            if (null === $game) {
                return new Step(StepOutcome::Missing);
            }
            $this->moderator->advance($game);
            if (null !== $game->game()->winner()) {
                return new Step(StepOutcome::Over, gameOver: true);
            }

            [$round, $day, $phase] = [$this->actorSeats($game), $game->game()->day(), $game->game()->phase()];
            $turns = [];
            foreach ($round as $seat) {
                if ($day !== $game->game()->day() || $phase !== $game->game()->phase() || !\in_array($seat, $this->actorSeats($game), true)) {
                    continue;
                }
                $turn = $seat === $game->humanSeat()
                    ? $this->humanTurns->play($gameId, $seat)
                    : $this->turns->play($gameId, $seat, $model, $day, $phase);
                $game = $this->load($gameId) ?? throw new \LogicException(\sprintf('Werewolf game "%s" vanished during a turn.', $gameId));
                $this->transcript->turn($gameId, $day, $phase, $turn);
                $turns[] = $turn;
                // The human seat was asked; the game waits for the answer instead of applying the deadline.
                if ($this->paused($gameId)) {
                    return new Step(StepOutcome::Paused, $turns);
                }
            }

            $missed = $day === $game->game()->day() && $phase === $game->game()->phase() && [] !== array_intersect($round, $this->actorSeats($game));
            if ($missed) {
                $this->moderator->expire($game);
            }
            $this->moderator->advance($game);

            $over = null !== $game->game()->winner();
            if ($over) {
                $this->transcript->finish($gameId, $game->game());
            }

            return new Step(StepOutcome::Played, $turns, $missed, $over);
        } finally {
            $lock->release();
        }
    }

    /** @return list<int> the seats the workflow lets act now, in seat order */
    private function actorSeats(WerewolfGame $game): array
    {
        $callers = array_map(
            static fn(int $seat): InMemoryUser => new InMemoryUser(WerewolfCallers::seat($seat), null),
            array_keys($game->game()->seats()),
        );

        return array_values(array_filter(
            array_map(WerewolfCallers::seatOf(...), $this->actors->actorsAmong($this->workflow, $game, $callers)),
            static fn(?int $seat): bool => null !== $seat,
        ));
    }

    /** Turns run through the MCP endpoint and flush there; read the game back from the database. */
    private function load(string $gameId): ?WerewolfGame
    {
        $this->entityManager->clear();

        return $this->entityManager->find(WerewolfGame::class, $gameId);
    }

    /** @phpstan-impure reads the pending inputs, which a turn may have just created */
    private function paused(string $gameId): bool
    {
        $ref = self::WORKFLOW . ':' . $gameId;
        foreach ([PendingInputStatus::Pending, PendingInputStatus::Answered] as $status) {
            foreach ($this->pendingInputs->withStatus($status) as $pending) {
                if ($ref === $pending->subjectRef) {
                    return true;
                }
            }
        }

        return false;
    }
}
