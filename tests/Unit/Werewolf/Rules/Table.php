<?php

declare(strict_types=1);

namespace App\Tests\Unit\Werewolf\Rules;

use App\Werewolf\Rules\Action\Action;
use App\Werewolf\Rules\Action\ChooseVictim;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\Action\Say;
use App\Werewolf\Rules\Action\Vote;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\Role;
use PHPUnit\Framework\Assert;

/**
 * Scripts games at a fixed table: 1 Ada and 2 Ben are wolves, 3 Cleo is the seer,
 * 4 Dan, 5 Eve, 6 Finn are villagers.
 */
final class Table
{
    public const int ADA = 1;
    public const int BEN = 2;
    public const int CLEO = 3;
    public const int DAN = 4;
    public const int EVE = 5;
    public const int FINN = 6;

    public static function game(int $seed = 7): Game
    {
        return Game::arranged($seed, [
            'Ada' => Role::Wolf,
            'Ben' => Role::Wolf,
            'Cleo' => Role::Seer,
            'Dan' => Role::Villager,
            'Eve' => Role::Villager,
            'Finn' => Role::Villager,
        ]);
    }

    public static function accept(Game $game, Action $action): void
    {
        $rejection = $game->submit($action);
        Assert::assertNull($rejection, $rejection->message ?? '');
    }

    /**
     * Plays a night and advances to dawn.
     *
     * @param array<int, int> $victims wolf seat => target
     */
    public static function night(Game $game, array $victims, ?int $inspect = null): void
    {
        Assert::assertSame(Phase::Night, $game->phase());
        foreach ($victims as $wolf => $target) {
            self::accept($game, new ChooseVictim($wolf, $target));
        }
        if (null !== $inspect) {
            self::accept($game, new Inspect(self::CLEO, $inspect));
        }
        $game->advance();
    }

    /** Dawn → discussion, every statement in turn, → vote. */
    public static function discuss(Game $game): void
    {
        Assert::assertSame(Phase::Dawn, $game->phase());
        $game->advance();
        while (null !== $speaker = $game->currentSpeaker()) {
            self::accept($game, new Say($speaker, \sprintf('Seat %d speaking.', $speaker)));
        }
        $game->advance();
        Assert::assertSame(Phase::DayVote, $game->phase());
    }

    /**
     * Casts the votes and resolves the day.
     *
     * @param array<int, int> $votes voter seat => target
     */
    public static function vote(Game $game, array $votes): void
    {
        foreach ($votes as $voter => $target) {
            self::accept($game, new Vote($voter, $target));
        }
        $game->advance();
    }
}
