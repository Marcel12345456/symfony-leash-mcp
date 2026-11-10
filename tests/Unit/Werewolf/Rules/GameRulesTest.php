<?php

declare(strict_types=1);

namespace App\Tests\Unit\Werewolf\Rules;

use App\Tests\Unit\Werewolf\Rules\Table as T;
use App\Werewolf\Rules\Action\ChooseVictim;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\Action\Say;
use App\Werewolf\Rules\Action\Vote;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\EventType;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\RejectionReason;
use App\Werewolf\Rules\Role;
use App\Werewolf\Rules\Seat;
use App\Werewolf\Rules\Winner;
use PHPUnit\Framework\TestCase;

/** One test per rule in PLAN §3.5. */
final class GameRulesTest extends TestCase
{
    private const array NAMES = ['Ada', 'Ben', 'Cleo', 'Dan', 'Eve', 'Finn'];

    public function testSixSeatsTwoWolvesOneSeerThreeVillagers(): void
    {
        $roles = array_map(static fn(Seat $s): string => $s->role->value, Game::start(1, self::NAMES)->seats());
        sort($roles);

        self::assertSame(['seer', 'villager', 'villager', 'villager', 'wolf', 'wolf'], $roles);
    }

    public function testRoleAssignmentIsSeeded(): void
    {
        $roles = static fn(int $seed): array => array_map(static fn(Seat $s): Role => $s->role, Game::start($seed, self::NAMES)->seats());

        self::assertSame($roles(42), $roles(42));
        $distinct = array_unique(array_map(static fn(int $seed): string => serialize($roles($seed)), range(1, 10)));
        self::assertGreaterThan(1, \count($distinct), 'different seeds deal different roles');
    }

    public function testGameNeedsSixSeatsAndTheComposition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Game::start(1, ['Ada', 'Ben', 'Cleo']);
    }

    public function testArrangedGameRejectsAWrongComposition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Game::arranged(1, ['A' => Role::Wolf, 'B' => Role::Wolf, 'C' => Role::Wolf, 'D' => Role::Seer, 'E' => Role::Villager, 'F' => Role::Villager]);
    }

    public function testCycleNightDawnDiscussionVoteNight(): void
    {
        $game = T::game();
        $phases = [$game->phase()];
        foreach (range(1, 4) as $_) {
            $game->advance();
            $phases[] = $game->phase();
        }

        self::assertSame([Phase::Night, Phase::Dawn, Phase::DayDiscussion, Phase::DayVote, Phase::Night], $phases);
        self::assertSame(2, $game->day());
    }

    public function testNightWaitsForEachLivingWolfAndTheSeer(): void
    {
        $game = T::game();
        self::assertSame([T::ADA, T::BEN, T::CLEO], $game->pendingSeats());

        T::accept($game, new ChooseVictim(T::ADA, T::DAN));
        T::accept($game, new Inspect(T::CLEO, T::BEN));
        self::assertSame([T::BEN], $game->pendingSeats());
    }

    public function testOnlyWolvesChooseAVictim(): void
    {
        $rejection = T::game()->submit(new ChooseVictim(T::DAN, T::EVE));

        self::assertSame(RejectionReason::NotYourRole, $rejection?->reason);
        self::assertSame('Only the wolves can do this.', $rejection->message);
    }

    public function testWolvesCannotChooseAWolf(): void
    {
        self::assertSame(RejectionReason::InvalidTarget, T::game()->submit(new ChooseVictim(T::ADA, T::BEN))?->reason);
    }

    public function testOnlyTheSeerInspects(): void
    {
        self::assertSame(RejectionReason::NotYourRole, T::game()->submit(new Inspect(T::ADA, T::CLEO))?->reason);
    }

    public function testEachSeatActsOncePerPhase(): void
    {
        $game = T::game();
        T::accept($game, new ChooseVictim(T::ADA, T::DAN));
        T::accept($game, new Inspect(T::CLEO, T::ADA));

        self::assertSame(RejectionReason::AlreadyActed, $game->submit(new ChooseVictim(T::ADA, T::EVE))?->reason);
        self::assertSame(RejectionReason::AlreadyActed, $game->submit(new Inspect(T::CLEO, T::BEN))?->reason);
    }

    public function testActionsOutsideTheirPhaseAreRejected(): void
    {
        $game = T::game();

        $rejection = $game->submit(new Vote(T::DAN, T::ADA));
        self::assertSame(RejectionReason::WrongPhase, $rejection?->reason);
        self::assertSame('This action belongs to day_vote; the game is in night.', $rejection->message);
        self::assertSame(RejectionReason::WrongPhase, $game->submit(new Say(T::DAN, 'hi'))?->reason);
    }

    public function testUnknownSeatsAreRejected(): void
    {
        $game = T::game();

        self::assertSame(RejectionReason::UnknownSeat, $game->submit(new ChooseVictim(9, T::DAN))?->reason);
        self::assertSame(RejectionReason::UnknownSeat, $game->submit(new ChooseVictim(T::ADA, 9))?->reason);
    }

    public function testDeadSeatsCannotAct(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);

        self::assertSame(RejectionReason::ActorDead, $game->submit(new Vote(T::DAN, T::ADA))?->reason);
        self::assertSame(RejectionReason::TargetDead, $game->submit(new Vote(T::EVE, T::DAN))?->reason);
    }

    public function testWolvesAgreeingKillTheirTargetAndDawnRevealsTheDeath(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::EVE, T::BEN => T::EVE]);

        self::assertSame(Phase::Dawn, $game->phase());
        self::assertFalse($game->seat(T::EVE)->alive);
        $death = self::last($game->events(), EventType::SeatKilled);
        self::assertSame(Audience::Public, $death->audience);
        self::assertSame('Eve (seat 5) was killed in the night.', $death->text);
    }

    public function testNoWolfChoiceBeforeTheDeadlineKillsNobody(): void
    {
        $game = T::game();
        $game->advance();

        self::assertCount(6, $game->livingSeats());
        self::assertSame('Nobody died tonight.', self::last($game->events(), EventType::NobodyKilled)->text);
    }

    public function testWolfChoicesAreVisibleToWolvesOnly(): void
    {
        $game = T::game();
        T::accept($game, new ChooseVictim(T::ADA, T::DAN));

        self::assertSame('Ada (seat 1) chose Dan (seat 4).', self::last($game->eventsVisibleTo(T::BEN), EventType::VictimChosen)->text);
        self::assertSame('The wolves are Ada (seat 1) and Ben (seat 2).', self::last($game->eventsVisibleTo(T::ADA), EventType::PackFormed)->text);
        foreach ([T::CLEO, T::DAN] as $seat) {
            $types = array_map(static fn(Event $e): EventType => $e->type, $game->eventsVisibleTo($seat));
            self::assertNotContains(EventType::VictimChosen, $types);
            self::assertNotContains(EventType::PackFormed, $types);
        }
    }

    public function testSeerLearnsWhetherTheSeatIsAWolfPrivately(): void
    {
        $game = T::game();
        T::accept($game, new Inspect(T::CLEO, T::BEN));

        self::assertSame('Ben (seat 2) is a wolf.', self::last($game->eventsVisibleTo(T::CLEO), EventType::SeatInspected)->text);
        self::assertNotContains(EventType::SeatInspected, array_map(static fn(Event $e): EventType => $e->type, $game->eventsVisibleTo(T::ADA)));

        $other = T::game();
        T::accept($other, new Inspect(T::CLEO, T::DAN));
        self::assertSame('Dan (seat 4) is not a wolf.', self::last($other->eventsVisibleTo(T::CLEO), EventType::SeatInspected)->text);
    }

    public function testSeerCannotInspectThemself(): void
    {
        self::assertSame(RejectionReason::InvalidTarget, T::game()->submit(new Inspect(T::CLEO, T::CLEO))?->reason);
    }

    public function testDiscussionIsRoundRobinWithTwoStatementsPerLivingSeat(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        $game->advance();

        $order = [];
        while (null !== $speaker = $game->currentSpeaker()) {
            self::assertSame([$speaker], $game->pendingSeats());
            $order[] = $speaker;
            T::accept($game, new Say($speaker, 'I suspect someone.'));
        }

        self::assertSame([1, 2, 3, 5, 6, 1, 2, 3, 5, 6], $order);
        self::assertSame([], $game->pendingSeats());
        self::assertSame(RejectionReason::NotYourTurn, $game->submit(new Say(T::ADA, 'One more.'))?->reason);
    }

    public function testASkippedSpeakerStaysSilentAndTheNextSeatSpeaks(): void
    {
        $game = T::game();
        $game->advance();
        $game->advance();

        $game->skipSpeaker();

        self::assertSame(2, $game->currentSpeaker());
        $last = $game->events()[\count($game->events()) - 1];
        self::assertSame(EventType::TurnSkipped, $last->type);
        self::assertSame(Audience::Public, $last->audience);
        self::assertSame('Ada (seat 1) stays silent.', $last->text);
    }

    public function testSpeakingOutOfTurnIsRejected(): void
    {
        $game = T::game();
        $game->advance();
        $game->advance();

        $rejection = $game->submit(new Say(T::EVE, 'Me first!'));
        self::assertSame(RejectionReason::NotYourTurn, $rejection?->reason);
        self::assertSame('It is Ada (seat 1)\'s turn to speak.', $rejection->message);
    }

    public function testStatementsHaveAtMost300Characters(): void
    {
        $game = T::game();
        $game->advance();
        $game->advance();

        self::assertSame(RejectionReason::InvalidMessage, $game->submit(new Say(T::ADA, str_repeat('ä', 301)))?->reason);
        self::assertSame(RejectionReason::InvalidMessage, $game->submit(new Say(T::ADA, '   '))?->reason);
        T::accept($game, new Say(T::ADA, str_repeat('ä', 300)));
        T::accept($game, new Say(T::BEN, '  Hello.  '));
        self::assertSame('Ben (seat 2): Hello.', self::last($game->events(), EventType::Statement)->text);
    }

    public function testEveryLivingSeatVotesOnceAndNotForThemself(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);

        self::assertSame([1, 2, 3, 5, 6], $game->pendingSeats());
        self::assertSame(RejectionReason::InvalidTarget, $game->submit(new Vote(T::EVE, T::EVE))?->reason);
        T::accept($game, new Vote(T::EVE, T::ADA));
        self::assertSame(RejectionReason::AlreadyActed, $game->submit(new Vote(T::EVE, T::BEN))?->reason);
        self::assertSame([1, 2, 3, 6], $game->pendingSeats());
        self::assertSame(Audience::Public, self::last($game->events(), EventType::VoteCast)->audience);
    }

    public function testPluralityIsEliminatedAndMissingVotesAbstain(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);
        T::vote($game, [T::CLEO => T::ADA]);

        self::assertFalse($game->seat(T::ADA)->alive);
        self::assertSame('Ada (seat 1) was eliminated by the vote.', self::last($game->events(), EventType::SeatEliminated)->text);
    }

    public function testNoVotesEliminateNobody(): void
    {
        $game = T::game();
        T::night($game, []);
        T::discuss($game);
        T::vote($game, []);

        self::assertCount(6, $game->livingSeats());
        self::assertSame('Nobody voted; nobody is eliminated.', self::last($game->events(), EventType::NobodyEliminated)->text);
    }

    public function testWolvesWinAtDawnWhenTheyReachParity(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);
        T::vote($game, []);
        self::assertNull($game->winner());

        T::night($game, [T::ADA => T::FINN, T::BEN => T::FINN]);

        self::assertSame(Winner::Wolves, $game->winner());
        self::assertSame(Phase::Over, $game->phase());
        self::assertStringContainsString('Ada (seat 1) was wolf', self::last($game->events(), EventType::GameOver)->text, 'game over reveals all roles');
    }

    public function testNoActionsAfterTheGameIsOver(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);
        T::vote($game, [T::ADA => T::EVE, T::BEN => T::EVE]);
        self::assertSame(Winner::Wolves, $game->winner());

        self::assertSame(RejectionReason::GameOver, $game->submit(new Vote(T::ADA, T::CLEO))?->reason);
        self::assertSame([], $game->pendingSeats());
        $this->expectException(\LogicException::class);
        $game->advance();
    }

    public function testDeadSeatsKeepTheirKnowledge(): void
    {
        $game = T::game();
        T::accept($game, new Inspect(T::CLEO, T::ADA));
        T::night($game, [T::ADA => T::CLEO, T::BEN => T::CLEO]);

        self::assertFalse($game->seat(T::CLEO)->alive);
        self::assertSame('Ada (seat 1) is a wolf.', self::last($game->eventsVisibleTo(T::CLEO), EventType::SeatInspected)->text);
    }

    /** @param list<Event> $events */
    private static function last(array $events, EventType $type): Event
    {
        $matching = array_values(array_filter($events, static fn(Event $e): bool => $type === $e->type));
        self::assertNotSame([], $matching, \sprintf('no %s event', $type->value));

        return $matching[\count($matching) - 1];
    }
}
