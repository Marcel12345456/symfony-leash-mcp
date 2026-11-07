<?php

declare(strict_types=1);

namespace App\Tests\Unit\Werewolf\Rules;

use App\Tests\Unit\Werewolf\Rules\Table as T;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\EventType;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\RejectionReason;
use App\Werewolf\Rules\Winner;
use PHPUnit\Framework\TestCase;

/** M3 acceptance: full games scripted move by move. */
final class ScriptedGamesTest extends TestCase
{
    public function testWolvesWin(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN], inspect: T::FINN);
        T::discuss($game);
        T::vote($game, [T::ADA => T::EVE, T::BEN => T::EVE, T::CLEO => T::ADA, T::EVE => T::ADA, T::FINN => T::EVE]);

        self::assertSame(Winner::Wolves, $game->winner());
        self::assertSame(Phase::Over, $game->phase());
        self::assertSame([T::ADA, T::BEN, T::CLEO, T::FINN], $game->livingSeats());
    }

    public function testVillageWins(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN], inspect: T::ADA);
        T::discuss($game);
        T::vote($game, [T::ADA => T::CLEO, T::BEN => T::CLEO, T::CLEO => T::ADA, T::EVE => T::ADA, T::FINN => T::ADA]);
        self::assertNull($game->winner());

        T::night($game, [T::BEN => T::EVE], inspect: T::BEN);
        T::discuss($game);
        T::vote($game, [T::BEN => T::FINN, T::CLEO => T::BEN, T::FINN => T::BEN]);

        self::assertSame(Winner::Village, $game->winner());
        self::assertSame([T::CLEO, T::FINN], $game->livingSeats());
        self::assertSame(2, $game->day());
    }

    public function testTieVoteEliminatesNobody(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);
        T::vote($game, [T::ADA => T::EVE, T::BEN => T::EVE, T::CLEO => T::ADA, T::EVE => T::ADA, T::FINN => T::CLEO]);

        self::assertNull($game->winner());
        self::assertSame(Phase::Night, $game->phase());
        self::assertSame(2, $game->day());
        self::assertSame([T::ADA, T::BEN, T::CLEO, T::EVE, T::FINN], $game->livingSeats());
        self::assertContains(EventType::NobodyEliminated, array_map(static fn($e) => $e->type, $game->events()));
    }

    public function testWolfDisagreementKillsOneOfTheirChoicesBySeed(): void
    {
        $victims = [];
        foreach (range(1, 20) as $seed) {
            $game = T::game($seed);
            T::night($game, [T::ADA => T::DAN, T::BEN => T::EVE]);
            $dead = array_values(array_diff(array_keys($game->seats()), $game->livingSeats()));
            self::assertCount(1, $dead);
            self::assertContains($dead[0], [T::DAN, T::EVE]);

            $replay = T::game($seed);
            T::night($replay, [T::ADA => T::DAN, T::BEN => T::EVE]);
            self::assertSame($game->livingSeats(), $replay->livingSeats(), 'same seed, same victim');
            $victims[$dead[0]] = true;
        }
        self::assertCount(2, $victims, 'over 20 seeds both choices occur');
    }

    public function testSeerInspectingADeadSeatIsRejected(): void
    {
        $game = T::game();
        T::night($game, [T::ADA => T::DAN, T::BEN => T::DAN]);
        T::discuss($game);
        T::vote($game, [T::ADA => T::EVE, T::BEN => T::EVE, T::CLEO => T::ADA, T::EVE => T::ADA, T::FINN => T::CLEO]);

        $rejection = $game->submit(new Inspect(T::CLEO, T::DAN));
        self::assertSame(RejectionReason::TargetDead, $rejection?->reason);
        self::assertStringContainsString('living seats: 1, 2, 3, 5, 6', $rejection->message);
        self::assertSame([T::ADA, T::BEN, T::CLEO], $game->pendingSeats(), 'the seer still owes an inspection');
    }

    public function testDrawAtTheDayCap(): void
    {
        $game = T::game();
        foreach (range(1, 4) as $day) {
            self::assertSame($day, $game->day());
            $game->advance(); // night deadline: no wolf chose, nobody dies
            T::discuss($game);
            T::vote($game, [T::ADA => T::CLEO, T::BEN => T::CLEO, T::CLEO => T::ADA, T::DAN => T::ADA]);
        }

        self::assertSame(Winner::Draw, $game->winner());
        self::assertSame(Phase::Over, $game->phase());
        self::assertCount(6, $game->livingSeats());
    }
}
