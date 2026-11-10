<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\ModelChoice;
use App\Agent\TurnOutcome;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\SeatTurn;
use App\Agent\Werewolf\StepOutcome;
use App\Agent\Werewolf\TranscriptWriter;
use App\Tests\Integration\Support\ScriptedSeatPlatform;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Winner;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Six scripted agents play a whole game over the in-process MCP endpoint (PLAN M5, decisions/030), runner steps only.
 * Wolves 1 and 5, seer 3, villagers 2, 4, 6. Night 1: wolf 5 stays silent (deadline, abstains), seat 2 dies.
 * Day 1 (two statements each): villager 4 tries `ww_choose_victim` (denied, game goes on), seat 6 tries four calls
 * (budget 3), seat 1 is voted out. Night 2: seat 4 dies. Day 2: seat 5 is voted out, the village wins.
 */
final class WerewolfAgentsTest extends WerewolfMcpCase
{
    public function testScriptedAgentsPlayAFullGame(): void
    {
        $this->createGame('g1', Game::arranged(7, self::ROLES));
        @unlink($this->service(TranscriptWriter::class)->path('g1'));
        $model = new ScriptedSeatPlatform([
            1 => [...self::act('ww_choose_victim', ['target' => 2]), ...self::say('Quiet night. Seat 3 talks too much.'), ...self::say('I am a villager.'), ...self::act('ww_vote', ['target' => 3])],
            2 => [],
            3 => [...self::act('ww_inspect', ['target' => 1]), ...self::say('I am the seer: seat 1 is a wolf.'), ...self::say('Vote seat 1.'), ...self::act('ww_vote', ['target' => 1]),
                ...self::act('ww_inspect', ['target' => 5]), ...self::say('Seat 5 is the other wolf.'), ...self::say('Vote seat 5.'), ...self::act('ww_vote', ['target' => 5])],
            4 => [self::call('ww_choose_victim', ['target' => 3]), ...self::say('I believe seat 3.'), ...self::say('Seat 1 it is.'), ...self::act('ww_vote', ['target' => 1])],
            5 => ['I wait and do nothing.', ...self::say('Seat 3 is lying.'), ...self::say('Seat 3 is a wolf.'), ...self::act('ww_vote', ['target' => 3]),
                ...self::act('ww_choose_victim', ['target' => 4]), ...self::say('Seat 3 again, really?'), ...self::say('Trust me.'), ...self::act('ww_vote', ['target' => 3])],
            6 => [new ToolCallResult([
                new ToolCall('0', 'ww_say', ['message' => 'Seat 1, explain yourself.']),
                new ToolCall('1', 'ww_say', ['message' => 'Again.']),
                new ToolCall('2', 'ww_say', ['message' => 'And again.']),
                new ToolCall('3', 'ww_say', ['message' => 'One too many.']),
            ]), 'done', ...self::say('Seat 1.'), ...self::act('ww_vote', ['target' => 1]),
                ...self::say('Seat 5 it is.'), ...self::say('Agreed.'), ...self::act('ww_vote', ['target' => 5])],
        ]);
        $choice = new ModelChoice($model->platform(), 'qwen3:8b');

        /** @var list<SeatTurn> $turns */
        $turns = [];
        $expired = 0;
        for ($n = 0; $n < 30 && null === $this->game('g1')->game()->winner(); ++$n) {
            $step = $this->service(NextActorRunner::class)->step('g1', $choice);
            self::assertSame(StepOutcome::Played, $step->outcome);
            $turns = [...$turns, ...$step->turns];
            $expired += $step->expired ? 1 : 0;
        }

        $game = $this->game('g1');
        self::assertSame(Winner::Village, $game->game()->winner());
        self::assertSame([1, 2, 4, 5], array_values(array_filter(array_keys($game->game()->seats()), static fn(int $n): bool => !$game->game()->seat($n)->alive)));
        self::assertSame(1, $expired, 'only night 1 waits for the silent wolf');
        self::assertNotContains(2, $model->asked, 'a dead seat is never asked');

        $victimDenials = array_values(array_filter($game->denials(), static fn(array $d): bool => 'ww_choose_victim' === $d['capability']));
        self::assertCount(1, $victimDenials);
        self::assertSame(['seat:4', 1], [$victimDenials[0]['caller'], $victimDenials[0]['day']], 'the villager is denied and logged; the game went on');
        self::assertCount(2, array_filter($game->denials(), static fn(array $d): bool => 'seat:6' === $d['caller'] && 'ww_say' === $d['capability']), 'seat 6 spoke, then its tools were gone');

        foreach ($turns as $turn) {
            self::assertLessThanOrEqual(3, $turn->toolCalls, 'seat ' . $turn->seat);
        }
        $six = array_values(array_filter($turns, static fn(SeatTurn $t): bool => 6 === $t->seat));
        self::assertSame(3, $six[0]->toolCalls, 'the fourth call never reaches the server');
        $silent = array_values(array_filter($turns, static fn(SeatTurn $t): bool => 5 === $t->seat))[0];
        self::assertSame([0, TurnOutcome::Done], [$silent->toolCalls, $silent->outcome]);

        $transcript = (string) file_get_contents($this->service(TranscriptWriter::class)->path('g1'));
        self::assertStringEndsWith("Result: village\n", $transcript);
        self::assertStringContainsString('I am the seer: seat 1 is a wolf.', $transcript);
    }

    /**
     * One tool call, then the model's closing sentence.
     *
     * @param array<string, mixed> $arguments
     *
     * @return list<ToolCallResult|string>
     */
    private static function act(string $tool, array $arguments): array
    {
        return [self::call($tool, $arguments), 'done'];
    }

    /** @return list<ToolCallResult|string> */
    private static function say(string $message): array
    {
        return self::act('ww_say', ['message' => $message]);
    }

    /** @param array<string, mixed> $arguments */
    private static function call(string $tool, array $arguments): ToolCallResult
    {
        return new ToolCallResult([new ToolCall('0', $tool, $arguments)]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
