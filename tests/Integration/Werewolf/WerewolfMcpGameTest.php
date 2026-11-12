<?php

declare(strict_types=1);

namespace App\Tests\Integration\Werewolf;

use App\Werewolf\Rules\EventType;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\Role;
use App\Werewolf\Security\WerewolfCallers;
use Mcp\Client;

/**
 * M4 done-signal: scripted seat clients (no LLM) play whole games over HTTP MCP. Each seat decides only from what
 * it can list and read; the moderator advances by itself. Along the way, villagers never see wolf tools or the den.
 */
final class WerewolfMcpGameTest extends WerewolfMcpCase
{
    private const array NAMES = ['Anna', 'Ben', 'Cara', 'Dan', 'Eve', 'Finn'];
    private const int MAX_ROUNDS = 100;

    public function testScriptedSeatsPlayAFullGame(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));

        $this->play('g1');

        $game = $this->game('g1')->game();
        self::assertSame(Phase::Over, $game->phase());
        self::assertSame('over', $this->game('g1')->getStatus());
        self::assertNotNull($game->winner());
        self::assertSame([], $this->game('g1')->denials(), 'scripted seats only call what they can see');
        $votes = array_filter($game->events(), static fn($e): bool => EventType::VoteCast === $e->type);
        self::assertNotSame([], $votes);
        foreach ($votes as $vote) {
            self::assertMatchesRegularExpression('/votes for .+ \(seat (\d)\): Seat \1 never answered me\.$/', $vote->text, 'the vote reason is in the public log');
        }
    }

    public function testSameSeedGivesTheSameGame(): void
    {
        $this->createGame('a', Game::start(42, self::NAMES));
        $this->createGame('b', Game::start(42, self::NAMES));

        $this->play('a');
        $this->play('b');

        $log = fn(string $id): array => array_map(static fn($e): string => $e->text, $this->game($id)->game()->events());
        self::assertSame(Phase::Over, $this->game('a')->game()->phase());
        self::assertSame($log('a'), $log('b'));
    }

    private function play(string $gameId): void
    {
        $roles = array_map(static fn($s): Role => $s->role, $this->game($gameId)->game()->seats());
        $clients = [];
        foreach (array_keys($roles) as $seat) {
            $clients[$seat] = $this->connect(WerewolfCallers::seat($seat), $gameId);
        }

        $round = 0;
        for (; $round < self::MAX_ROUNDS; ++$round) {
            $public = self::read($clients[1], $gameId, 'public-log');
            if ('over' === $public['phase']) {
                break;
            }
            foreach ($clients as $seat => $client) {
                $tools = self::toolNames($client);
                if (Role::Villager === $roles[$seat]) {
                    self::assertNotContains('ww_choose_victim', $tools);
                    self::assertNotContains('wolf-den', self::resourceNames($client));
                }
                foreach ($tools as $tool) {
                    $result = $client->callTool($tool, $this->decide($client, $gameId, $seat, $tool));
                    self::assertFalse($result->isError, \sprintf('seat %d %s: %s', $seat, $tool, self::text($result)));
                }
            }
        }

        foreach ($clients as $client) {
            $client->disconnect();
        }
        self::assertLessThan(self::MAX_ROUNDS, $round, 'the game did not end');
    }

    /** @return array<string, mixed> deterministic arguments from what this seat can read */
    private function decide(Client $client, string $gameId, int $seat, string $tool): array
    {
        $living = self::ints(array_map(
            static fn(mixed $s): mixed => \is_array($s) && true === ($s['alive'] ?? null) ? $s['seat'] ?? null : null,
            (array) (self::read($client, $gameId, 'public-log')['seats'] ?? []),
        ));
        $others = array_values(array_diff($living, [$seat]));

        return match ($tool) {
            'ww_choose_victim' => ['target' => array_values(array_diff($others, self::ints((array) (self::read($client, $gameId, 'wolf-den')['pack'] ?? []))))[0]],
            'ww_inspect' => ['target' => $others[0]],
            'ww_vote' => ['target' => $others[0], 'reason' => \sprintf('Seat %d never answered me.', $others[0])],
            'ww_say' => ['message' => \sprintf('Seat %d here. I suspect seat %d.', $seat, $others[0])],
            default => self::fail('Unexpected tool ' . $tool),
        };
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<int>
     */
    private static function ints(array $values): array
    {
        return array_values(array_filter($values, \is_int(...)));
    }
}
