<?php

declare(strict_types=1);

namespace App\Tests\Integration\Werewolf;

use App\Werewolf\Rules\Action\ChooseVictim;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Security\WerewolfCallers;

/** M4 done-signal: who sees which tools and resources, per seat role and life, and what a forbidden call does. */
final class WerewolfVisibilityTest extends WerewolfMcpCase
{
    public function testNightOneEachSeatSeesOnlyItsOwnToolsAndResources(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $expected = [
            1 => [['ww_choose_victim'], ['public-log', 'me', 'wolf-den']],
            2 => [[], ['public-log', 'me']],
            3 => [['ww_inspect'], ['public-log', 'me', 'seer-notes']],
            4 => [[], ['public-log', 'me']],
            5 => [['ww_choose_victim'], ['public-log', 'me', 'wolf-den']],
            6 => [[], ['public-log', 'me']],
        ];
        foreach ($expected as $seat => [$tools, $resources]) {
            $client = $this->connect(WerewolfCallers::seat($seat), 'g1');
            self::assertSame($tools, self::toolNames($client), 'tools of seat ' . $seat);
            self::assertSame($resources, self::resourceNames($client), 'resources of seat ' . $seat);
            $client->disconnect();
        }
    }

    public function testAVillagerCallingChooseVictimIsDeniedLoggedAndChangesNothing(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $villager = $this->connect(WerewolfCallers::seat(2), 'g1');
        $result = $villager->callTool('ww_choose_victim', ['target' => 3]);
        $villager->disconnect();

        self::assertTrue($result->isError);
        self::assertSame('"ww_choose_victim" is not available to your role in state "night". No tools are available to you now.', self::text($result));
        $game = $this->game('g1');
        self::assertSame([['caller' => 'seat:2', 'capability' => 'ww_choose_victim', 'places' => ['night'], 'day' => 1]], $game->denials());
        self::assertCount(\count(Game::arranged(7, self::ROLES)->events()), $game->game()->events(), 'no event was added');
    }

    public function testAVillagerReadingTheWolfDenIsDeniedAndLogged(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $villager = $this->connect(WerewolfCallers::seat(2), 'g1');
        try {
            $villager->readResource('werewolf://game/g1/wolf-den');
            self::fail('A villager must not read the wolf den.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Readable now: werewolf://game/g1/public-log, werewolf://game/g1/me.', $e->getMessage());
        } finally {
            $villager->disconnect();
        }

        self::assertSame('werewolf://game/g1/wolf-den', $this->game('g1')->denials()[0]['capability'] ?? null);
    }

    public function testADeadSeatSeesOnlyThePublicLog(): void
    {
        $game = Game::arranged(7, self::ROLES);
        $game->submit(new ChooseVictim(1, 3));
        $game->submit(new ChooseVictim(5, 3));
        $game->submit(new Inspect(3, 1));
        $game->advance();
        self::assertFalse($game->seat(3)->alive);
        $this->createGame('g1', $game);

        $dead = $this->connect(WerewolfCallers::seat(3), 'g1');
        self::assertSame([], self::toolNames($dead));
        self::assertSame(['public-log'], self::resourceNames($dead));
        $dead->disconnect();
    }

    public function testModeratorTransitionsAreVisibleToTheModeratorOnly(): void
    {
        // All night actions are in, but nobody advanced yet: the moderator's break_dawn is enabled.
        $game = Game::arranged(7, self::ROLES);
        $game->submit(new ChooseVictim(1, 2));
        $game->submit(new ChooseVictim(5, 2));
        $game->submit(new Inspect(3, 1));
        $this->createGame('g1', $game);

        foreach (range(1, Game::SEATS) as $seat) {
            $client = $this->connect(WerewolfCallers::seat($seat), 'g1');
            self::assertSame([], self::toolNames($client), 'seat ' . $seat);
            $client->disconnect();
        }

        $moderator = $this->connect(WerewolfCallers::MODERATOR, 'g1');
        self::assertSame(['ww_break_dawn'], self::toolNames($moderator));
        self::assertSame(['public-log'], self::resourceNames($moderator));
        $result = $moderator->callTool('ww_break_dawn', []);
        $moderator->disconnect();

        self::assertFalse($result->isError, self::text($result));
        // The moderator keeps going while nobody is waited for: dawn → day_discussion.
        self::assertSame(Phase::DayDiscussion, $this->game('g1')->game()->phase());
        self::assertSame('day_discussion', $this->game('g1')->getStatus());
        self::assertFalse($this->game('g1')->game()->seat(2)->alive);
    }

    public function testASeatThatActedLosesItsTool(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $wolf = $this->connect(WerewolfCallers::seat(1), 'g1');
        $result = $wolf->callTool('ww_choose_victim', ['target' => 2]);
        self::assertFalse($result->isError, self::text($result));
        self::assertSame([], self::toolNames($wolf));
        self::assertSame(['The wolves are Anna (seat 1) and Eve (seat 5).', 'Anna (seat 1) chose Ben (seat 2).'], array_column(self::list(self::read($wolf, 'g1', 'wolf-den'), 'events'), 'text'));
        $wolf->disconnect();
    }

    public function testRejectedActionIsAnActionableError(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $wolf = $this->connect(WerewolfCallers::seat(1), 'g1');
        $result = $wolf->callTool('ww_choose_victim', ['target' => 5]);
        $wolf->disconnect();

        self::assertTrue($result->isError);
        self::assertSame('Wolves cannot choose a wolf; choose a living non-wolf seat.', self::text($result));
        self::assertSame(['ww_choose_victim'], self::toolNames($this->connect(WerewolfCallers::seat(1), 'g1')));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    private static function list(array $data, string $key): array
    {
        $list = $data[$key] ?? null;
        self::assertIsArray($list);

        return array_values(array_filter($list, \is_array(...)));
    }
}
