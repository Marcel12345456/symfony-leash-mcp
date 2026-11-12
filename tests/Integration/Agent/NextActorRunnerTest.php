<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\ModelChoice;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\StepOutcome;
use App\Agent\Werewolf\TranscriptWriter;
use App\Tests\Integration\Support\ScriptedSeatPlatform;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall as HumanToolCall;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputs;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Lock\LockFactory;

/** One runner step on night 1 (wolves 1 and 5, seer 3), agents over the in-process MCP endpoint. */
final class NextActorRunnerTest extends WerewolfMcpCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createGame('g1', Game::arranged(7, self::ROLES));
        @unlink($this->service(TranscriptWriter::class)->path('g1'));
    }

    public function testNightRoundPlaysEveryActorAndASilentWolfMissesTheDeadline(): void
    {
        $model = new ScriptedSeatPlatform([
            1 => [new ToolCallResult([new ToolCall('c1', 'ww_choose_victim', ['target' => 2])]), 'I chose seat 2.'],
            3 => [new ToolCallResult([new ToolCall('c1', 'ww_inspect', ['target' => 1])]), 'I inspected seat 1.'],
            5 => [new TransportException('Idle timeout reached')],
        ]);

        $step = $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));

        self::assertSame(StepOutcome::Played, $step->outcome);
        self::assertSame([1, 1, 3, 3, 5], $model->asked, 'two model requests per acting seat: the tool call, then the answer');
        self::assertCount(3, $step->turns);
        self::assertTrue($step->expired);
        $game = $this->game('g1')->game();
        self::assertSame(Phase::DayDiscussion, $game->phase());
        self::assertFalse($game->seat(2)->alive);
        $transcript = (string) file_get_contents($this->service(TranscriptWriter::class)->path('g1'));
        self::assertStringContainsString('seat 5 (timed_out, 0 tool calls)', $transcript);
    }

    public function testTranscriptLabelsATurnWithThePhaseItWasPlayedIn(): void
    {
        $model = new ScriptedSeatPlatform([
            1 => [new ToolCallResult([new ToolCall('c1', 'ww_choose_victim', ['target' => 2])]), 'I chose seat 2.'],
            3 => [new ToolCallResult([new ToolCall('c1', 'ww_inspect', ['target' => 1])]), 'I inspected seat 1.'],
            5 => [new ToolCallResult([new ToolCall('c1', 'ww_choose_victim', ['target' => 2])]), 'I chose seat 2 too.'],
        ]);

        $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));

        self::assertSame(Phase::DayDiscussion, $this->game('g1')->game()->phase(), 'the last wolf call ended the night');
        $transcript = (string) file_get_contents($this->service(TranscriptWriter::class)->path('g1'));
        self::assertStringContainsString('## Day 1, night: seat 5 (done, 1 tool call)', $transcript);
        self::assertStringNotContainsString('day_discussion: seat', $transcript);
    }

    public function testHeldLockSkipsTheStep(): void
    {
        $model = new ScriptedSeatPlatform();
        $lock = $this->service(LockFactory::class)->createLock(NextActorRunner::lockKey('g1'));
        self::assertTrue($lock->acquire());

        try {
            $step = $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));
        } finally {
            $lock->release();
        }

        self::assertSame(StepOutcome::Locked, $step->outcome);
        self::assertSame([], $model->asked);
    }

    public function testOpenHumanInputPausesTheGame(): void
    {
        $model = new ScriptedSeatPlatform();
        $call = new HumanToolCall('http://localhost/mcp/werewolf_game/g1', 'werewolf_game:g1', 'seat:1', 'ww_choose_victim', ['target' => 2]);
        $this->service(PendingInputs::class)->save(new PendingInput($call, new InputRequired([], 'state'), new \DateTimeImmutable()));

        $step = $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));

        self::assertSame(StepOutcome::Paused, $step->outcome);
        self::assertSame([], $model->asked);
        self::assertSame(Phase::Night, $this->game('g1')->game()->phase());
    }

    private function runner(): NextActorRunner
    {
        return $this->service(NextActorRunner::class);
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
