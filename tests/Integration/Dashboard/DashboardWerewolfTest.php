<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Tests\Integration\Werewolf\WerewolfMcpCase;

/**
 * M7 done-signal (1), PLAN §3.5/§3.7: after the replayed demo game the spectator JSON tells the story — public log,
 * winner, the denied `ww_choose_victim` — and roles appear only when the viewer asks for them.
 */
final class DashboardWerewolfTest extends WerewolfMcpCase
{
    public function testSpectatorViewOfTheReplayedDemoGame(): void
    {
        ReplayedDemoGame::play($this->browser->getContainer());

        $crawler = $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame('Werewolf', $crawler->filter('[role=tab][aria-selected=true]')->text());
        self::assertSame(ReplayedDemoGame::ID, $crawler->filter('#game option[selected]')->attr('value'));

        $hidden = $this->view(false);
        self::assertNotNull($hidden['winner']);
        self::assertSame(['public'], array_values(array_unique(array_column($hidden['events'], 'audience'))));
        self::assertSame([], array_filter(array_column($hidden['seats'], 'role'), is_string(...)));
        self::assertSame('game_over', array_last($hidden['events'])['type'] ?? null);
        self::assertContains(['caller' => 'seat:3', 'capability' => 'ww_choose_victim', 'places' => ['day_discussion'], 'day' => 1], $hidden['denials']);
        foreach ($hidden['seats'] as $seat) {
            self::assertSame([], $seat['roles']);
            self::assertSame([], array_filter(array_column($seat['resources'], 'content'), static fn(mixed $c): bool => null !== $c), 'no resource content without reveal');
        }

        $revealed = $this->view(true);
        $roles = array_column($revealed['seats'], 'role', 'seat');
        self::assertSame(['seer' => 1, 'villager' => 3, 'wolf' => 2], self::counted($roles));
        self::assertSame('villager', $roles[3], 'the scripted seat');
        self::assertGreaterThan(\count($hidden['events']), \count($revealed['events']), 'private events (wolf den, seer notes) appear');
    }

    public function testUnknownGameIs404(): void
    {
        $this->browser->request('GET', '/dashboard/werewolf/nope');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<array-key, ?string> $roles
     *
     * @return array<string, int>
     */
    private static function counted(array $roles): array
    {
        $counts = array_count_values(array_filter($roles, is_string(...)));
        ksort($counts);

        return $counts;
    }

    /** @return array{winner: ?string, events: list<array{type: string, audience: string}>, seats: list<array{seat: int, role: ?string, roles: list<string>, resources: list<array{content: mixed}>}>, denials: list<array<string, mixed>>} */
    private function view(bool $reveal): array
    {
        $this->browser->request('GET', '/dashboard/werewolf/' . ReplayedDemoGame::ID . ($reveal ? '?reveal=1' : ''));
        self::assertResponseIsSuccessful();
        /** @var array{winner: ?string, events: list<array{type: string, audience: string}>, seats: list<array{seat: int, role: ?string, roles: list<string>, resources: list<array{content: mixed}>}>, denials: list<array<string, mixed>>} $view JSON of WerewolfSpectator::view() */
        $view = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $view;
    }
}
