<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

use App\Werewolf\Rules\Action\Action;
use App\Werewolf\Rules\Action\ChooseVictim;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\Action\Say;
use App\Werewolf\Rules\Action\Vote;

/**
 * One Werewolf game (PLAN §3.5): night → dawn → day_discussion → day_vote → night ….
 *
 * Seats submit actions; the moderator calls advance() when pendingSeats() is empty or the
 * phase deadline passed. Missing actions count as abstentions. Mutable on purpose: it is the
 * aggregate that the workflow moves through its places.
 */
final class Game
{
    public const int SEATS = 6;
    public const int STATEMENTS_PER_SEAT = 2;
    public const int MAX_STATEMENT_LENGTH = 300;
    public const int MAX_DAYS = 4;

    private Phase $phase = Phase::Night;
    private int $day = 1;
    private ?Winner $winner = null;

    /** @var list<Event> */
    private array $events = [];

    /** @var array<int, int> wolf seat => chosen target */
    private array $victimChoices = [];
    private ?int $inspected = null;

    /** @var list<int> */
    private array $speakingOrder = [];
    private int $turn = 0;

    /** @var array<int, int> voter seat => target */
    private array $votes = [];

    /**
     * @param array<int, Seat> $seats by seat number, 1-based
     */
    private function __construct(
        public readonly int $seed,
        private array $seats,
    ) {
        $this->log(EventType::GameStarted, Audience::Public, \sprintf('Game started with %s.', implode(', ', array_map(static fn(Seat $s): string => $s->label(), $seats))));
        $wolves = array_map(static fn(Seat $s): string => $s->label(), $this->seatsWith(Role::Wolf));
        $this->log(EventType::PackFormed, Audience::Wolves, \sprintf('The wolves are %s.', implode(' and ', $wolves)));
        $this->log(EventType::PhaseStarted, Audience::Public, 'Night 1 falls.');
    }

    /**
     * Seats in the given order; roles assigned by the seed.
     *
     * @param list<string> $names
     */
    public static function start(int $seed, array $names): self
    {
        $roles = (new SeededRandom($seed))->shuffle('roles', Role::COMPOSITION);
        if (\count($names) !== \count($roles)) {
            throw new \InvalidArgumentException(\sprintf('A game needs exactly %d seats, got %d.', self::SEATS, \count($names)));
        }

        return self::arranged($seed, array_combine($names, $roles));
    }

    /**
     * Fixed roles, e.g. for a recorded demo game or tests. Composition must still be 2/1/3.
     *
     * @param array<array-key, Role> $rolesByName names in seat order
     */
    public static function arranged(int $seed, array $rolesByName): self
    {
        if (self::SEATS !== \count($rolesByName)) {
            throw new \InvalidArgumentException(\sprintf('A game needs exactly %d seats, got %d.', self::SEATS, \count($rolesByName)));
        }
        $composition = array_map(static fn(Role $r): string => $r->value, Role::COMPOSITION);
        $given = array_map(static fn(Role $r): string => $r->value, array_values($rolesByName));
        sort($composition);
        sort($given);
        if ($composition !== $given) {
            throw new \InvalidArgumentException('A game needs 2 wolves, 1 seer and 3 villagers.');
        }

        $seats = [];
        $number = 1;
        foreach ($rolesByName as $name => $role) {
            if ('' === trim((string) $name)) {
                throw new \InvalidArgumentException('Seat names must not be empty.');
            }
            $seats[$number] = new Seat($number, (string) $name, $role);
            ++$number;
        }

        return new self($seed, $seats);
    }

    public function phase(): Phase
    {
        return $this->phase;
    }

    public function day(): int
    {
        return $this->day;
    }

    public function winner(): ?Winner
    {
        return $this->winner;
    }

    /** @return array<int, Seat> */
    public function seats(): array
    {
        return $this->seats;
    }

    public function seat(int $number): Seat
    {
        return $this->seats[$number] ?? throw new \OutOfRangeException(\sprintf('No seat %d.', $number));
    }

    /** @return list<int> */
    public function livingSeats(): array
    {
        return array_keys(array_filter($this->seats, static fn(Seat $s): bool => $s->alive));
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Public events plus the wolf den for wolves and the seer's notes for the seer.
     * Dead seats keep what they knew.
     *
     * @return list<Event>
     */
    public function eventsVisibleTo(int $seat): array
    {
        $role = $this->seat($seat)->role;

        return array_values(array_filter($this->events, static fn(Event $e): bool => match ($e->audience) {
            Audience::Public => true,
            Audience::Wolves => Role::Wolf === $role,
            Audience::Seer => Role::Seer === $role,
        }));
    }

    /** The seat whose statement is due in day_discussion, else null. */
    public function currentSpeaker(): ?int
    {
        return Phase::DayDiscussion === $this->phase ? ($this->speakingOrder[$this->turn] ?? null) : null;
    }

    /**
     * Seats whose action the current phase still waits for. Empty → the moderator may advance.
     *
     * @return list<int>
     */
    public function pendingSeats(): array
    {
        return match ($this->phase) {
            Phase::Night => array_values(array_filter(
                $this->livingSeats(),
                fn(int $n): bool => match ($this->seats[$n]->role) {
                    Role::Wolf => !isset($this->victimChoices[$n]),
                    Role::Seer => null === $this->inspected,
                    Role::Villager => false,
                },
            )),
            Phase::DayDiscussion => null === $this->currentSpeaker() ? [] : [$this->currentSpeaker()],
            Phase::DayVote => array_values(array_filter($this->livingSeats(), fn(int $n): bool => !isset($this->votes[$n]))),
            Phase::Dawn, Phase::Over => [],
        };
    }

    public function submit(Action $action): ?Rejection
    {
        $rejection = $this->check($action);
        if (null !== $rejection) {
            return $rejection;
        }

        $actor = $this->seats[$action->actor()];
        match (true) {
            $action instanceof ChooseVictim => $this->chooseVictim($actor, $this->seats[$action->target]),
            $action instanceof Inspect => $this->inspect($actor, $this->seats[$action->target]),
            $action instanceof Say => $this->say($actor, trim($action->message)),
            $action instanceof Vote => $this->vote($actor, $this->seats[$action->target]),
            default => throw new \LogicException(\sprintf('Unsupported action %s.', $action::class)),
        };

        return null;
    }

    /** Resolves the current phase (missing actions abstain) and moves to the next one. */
    public function advance(): void
    {
        match ($this->phase) {
            Phase::Night => $this->resolveNight(),
            Phase::Dawn => $this->startDiscussion(),
            Phase::DayDiscussion => $this->enter(Phase::DayVote, \sprintf('Day %d: the vote opens.', $this->day)),
            Phase::DayVote => $this->resolveVote(),
            Phase::Over => throw new \LogicException('The game is over.'),
        };
    }

    private function check(Action $action): ?Rejection
    {
        if (Phase::Over === $this->phase) {
            return new Rejection(RejectionReason::GameOver, 'The game is over; no more actions.');
        }
        $actor = $this->seats[$action->actor()] ?? null;
        if (null === $actor) {
            return new Rejection(RejectionReason::UnknownSeat, \sprintf('There is no seat %d.', $action->actor()));
        }
        if (!$actor->alive) {
            return new Rejection(RejectionReason::ActorDead, 'You are dead; you can only read the public log.');
        }

        [$phase, $role] = match (true) {
            $action instanceof ChooseVictim => [Phase::Night, Role::Wolf],
            $action instanceof Inspect => [Phase::Night, Role::Seer],
            $action instanceof Say => [Phase::DayDiscussion, null],
            $action instanceof Vote => [Phase::DayVote, null],
            default => throw new \LogicException(\sprintf('Unsupported action %s.', $action::class)),
        };
        if ($phase !== $this->phase) {
            return new Rejection(RejectionReason::WrongPhase, \sprintf('This action belongs to %s; the game is in %s.', $phase->value, $this->phase->value));
        }
        if (null !== $role && $role !== $actor->role) {
            return new Rejection(RejectionReason::NotYourRole, \sprintf('Only the %s can do this.', Role::Wolf === $role ? 'wolves' : $role->value));
        }

        return match (true) {
            $action instanceof ChooseVictim => $this->checkVictim($action),
            $action instanceof Inspect => $this->checkInspection($action),
            $action instanceof Say => $this->checkStatement($action),
            $action instanceof Vote => $this->checkVote($action),
        };
    }

    private function checkVictim(ChooseVictim $action): ?Rejection
    {
        if (isset($this->victimChoices[$action->seat])) {
            return new Rejection(RejectionReason::AlreadyActed, 'You already chose a victim tonight.');
        }

        return $this->checkTarget($action->target)
            ?? (Role::Wolf === $this->seats[$action->target]->role
                ? new Rejection(RejectionReason::InvalidTarget, 'Wolves cannot choose a wolf; choose a living non-wolf seat.')
                : null);
    }

    private function checkInspection(Inspect $action): ?Rejection
    {
        if (null !== $this->inspected) {
            return new Rejection(RejectionReason::AlreadyActed, 'You already inspected a seat tonight.');
        }

        return $this->checkTarget($action->target)
            ?? ($action->target === $action->seat
                ? new Rejection(RejectionReason::InvalidTarget, 'You know your own role; inspect another living seat.')
                : null);
    }

    private function checkStatement(Say $action): ?Rejection
    {
        $speaker = $this->currentSpeaker();
        if ($speaker !== $action->seat) {
            return new Rejection(RejectionReason::NotYourTurn, null === $speaker
                ? 'Discussion is complete; wait for the vote.'
                : \sprintf('It is %s\'s turn to speak.', $this->seats[$speaker]->label()));
        }
        $length = mb_strlen(trim($action->message));
        if (0 === $length || $length > self::MAX_STATEMENT_LENGTH) {
            return new Rejection(RejectionReason::InvalidMessage, \sprintf('A statement needs 1 to %d characters, got %d.', self::MAX_STATEMENT_LENGTH, $length));
        }

        return null;
    }

    private function checkVote(Vote $action): ?Rejection
    {
        if (isset($this->votes[$action->seat])) {
            return new Rejection(RejectionReason::AlreadyActed, 'You already voted today.');
        }

        return $this->checkTarget($action->target)
            ?? ($action->target === $action->seat
                ? new Rejection(RejectionReason::InvalidTarget, 'You cannot vote for yourself; vote for another living seat.')
                : null);
    }

    private function checkTarget(int $target): ?Rejection
    {
        $seat = $this->seats[$target] ?? null;
        if (null === $seat) {
            return new Rejection(RejectionReason::UnknownSeat, \sprintf('There is no seat %d; living seats: %s.', $target, implode(', ', $this->livingSeats())));
        }
        if (!$seat->alive) {
            return new Rejection(RejectionReason::TargetDead, \sprintf('%s is dead; living seats: %s.', $seat->label(), implode(', ', $this->livingSeats())));
        }

        return null;
    }

    private function chooseVictim(Seat $wolf, Seat $target): void
    {
        $this->victimChoices[$wolf->number] = $target->number;
        $this->log(EventType::VictimChosen, Audience::Wolves, \sprintf('%s chose %s.', $wolf->label(), $target->label()), $wolf->number, $target->number);
    }

    private function inspect(Seat $seer, Seat $target): void
    {
        $this->inspected = $target->number;
        $this->log(EventType::SeatInspected, Audience::Seer, \sprintf('%s is %s.', $target->label(), Role::Wolf === $target->role ? 'a wolf' : 'not a wolf'), $seer->number, $target->number);
    }

    private function say(Seat $speaker, string $message): void
    {
        $this->log(EventType::Statement, Audience::Public, \sprintf('%s: %s', $speaker->label(), $message), $speaker->number);
        ++$this->turn;
    }

    private function vote(Seat $voter, Seat $target): void
    {
        $this->votes[$voter->number] = $target->number;
        $this->log(EventType::VoteCast, Audience::Public, \sprintf('%s votes for %s.', $voter->label(), $target->label()), $voter->number, $target->number);
    }

    /** Wolves agree → that seat dies; they disagree → seeded pick among their choices; no choice → nobody dies. */
    private function resolveNight(): void
    {
        $choices = array_values(array_unique($this->victimChoices));
        sort($choices);
        $this->victimChoices = [];
        $this->inspected = null;

        $this->enter(Phase::Dawn, \sprintf('Dawn of day %d.', $this->day));
        if ([] === $choices) {
            $this->log(EventType::NobodyKilled, Audience::Public, 'Nobody died tonight.');
        } else {
            $victim = (new SeededRandom($this->seed))->pick(\sprintf('night:%d:victim', $this->day), $choices);
            $this->kill($victim, EventType::SeatKilled, '%s was killed in the night.');
        }
        $this->checkWin();
    }

    private function startDiscussion(): void
    {
        $living = $this->livingSeats();
        $this->speakingOrder = array_merge(...array_fill(0, self::STATEMENTS_PER_SEAT, $living));
        $this->turn = 0;
        $this->enter(Phase::DayDiscussion, \sprintf('Day %d: discussion, %d statements each in seat order.', $this->day, self::STATEMENTS_PER_SEAT));
    }

    /** Plurality is eliminated; a tie or no votes eliminates nobody. After day MAX_DAYS without a winner: draw. */
    private function resolveVote(): void
    {
        $tally = array_count_values($this->votes);
        $this->votes = [];
        $top = [] === $tally ? [] : array_keys($tally, max($tally), true);

        if (1 === \count($top)) {
            $this->kill($top[0], EventType::SeatEliminated, '%s was eliminated by the vote.');
        } else {
            $this->log(EventType::NobodyEliminated, Audience::Public, [] === $tally ? 'Nobody voted; nobody is eliminated.' : 'The vote is tied; nobody is eliminated.');
        }

        if ($this->checkWin()) {
            return;
        }
        if ($this->day >= self::MAX_DAYS) {
            $this->finish(Winner::Draw, \sprintf('Draw: no winner after %d days.', self::MAX_DAYS));

            return;
        }
        ++$this->day;
        $this->enter(Phase::Night, \sprintf('Night %d falls.', $this->day));
    }

    private function kill(int $number, EventType $type, string $format): void
    {
        $this->seats[$number] = $this->seats[$number]->dead();
        $this->log($type, Audience::Public, \sprintf($format, $this->seats[$number]->label()), target: $number);
    }

    /** Village wins when no wolf is alive; wolves win when they are at least as many as the rest. */
    private function checkWin(): bool
    {
        $wolves = \count(array_filter($this->livingSeats(), fn(int $n): bool => Role::Wolf === $this->seats[$n]->role));
        $others = \count($this->livingSeats()) - $wolves;

        if (0 === $wolves) {
            $this->finish(Winner::Village, 'The village wins: no wolves are left.');
        } elseif ($wolves >= $others) {
            $this->finish(Winner::Wolves, 'The wolves win: they are as many as the villagers.');
        }

        return null !== $this->winner;
    }

    private function finish(Winner $winner, string $text): void
    {
        $this->winner = $winner;
        $this->phase = Phase::Over;
        $roles = implode(', ', array_map(static fn(Seat $s): string => \sprintf('%s was %s', $s->label(), $s->role->value), $this->seats));
        $this->log(EventType::GameOver, Audience::Public, \sprintf('%s %s.', $text, $roles));
    }

    private function enter(Phase $phase, string $text): void
    {
        $this->phase = $phase;
        $this->log(EventType::PhaseStarted, Audience::Public, $text);
    }

    /** @return list<Seat> */
    private function seatsWith(Role $role): array
    {
        return array_values(array_filter($this->seats, static fn(Seat $s): bool => $role === $s->role));
    }

    private function log(EventType $type, Audience $audience, string $text, ?int $actor = null, ?int $target = null): void
    {
        $this->events[] = new Event($this->day, $this->phase, $type, $audience, $text, $actor, $target);
    }
}
