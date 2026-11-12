<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\WerewolfSpectator;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Security\WerewolfCallers;

/**
 * M7 done-signal (2), PLAN §3.5: the spectator's "what this seat sees" panel equals what the MCP server lists for
 * that seat's bearer token — every seat, after every step of the replayed demo game, so every phase is covered.
 */
final class SeatPanelMatchesServerTest extends WerewolfMcpCase
{
    public function testPanelEqualsToolsAndResourcesListedOverMcpInEveryPhase(): void
    {
        $phases = [];
        $checked = 0;
        ReplayedDemoGame::play($this->browser->getContainer(), function (int $step) use (&$phases, &$checked): void {
            // The service behind /dashboard/werewolf/{id}.json (DashboardWerewolfTest covers the route).
            $view = ReplayedDemoGame::service($this->browser->getContainer(), WerewolfSpectator::class)->view(ReplayedDemoGame::ID, false);
            self::assertNotNull($view);
            $phases[$view['phase']] = true;

            foreach ($view['seats'] as $seat) {
                $client = $this->connect(WerewolfCallers::seat($seat['seat']), ReplayedDemoGame::ID);
                $where = \sprintf('step %d, day %d %s, seat %d', $step, $view['day'], $view['phase'], $seat['seat']);
                self::assertSame(self::toolNames($client), array_column($seat['tools'], 'name'), 'tools at ' . $where);
                self::assertSame(self::resourceNames($client), array_column($seat['resources'], 'name'), 'resources at ' . $where);
                $client->disconnect();
                ++$checked;
            }
        });

        // Dawn never rests between steps: the moderator moves through it inside a step.
        self::assertEqualsCanonicalizing(['night', 'day_discussion', 'day_vote', 'over'], array_keys($phases));
        self::assertGreaterThan(60, $checked);
    }
}
