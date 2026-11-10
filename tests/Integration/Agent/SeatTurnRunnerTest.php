<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\McpConnector;
use App\Agent\ModelChoice;
use App\Agent\TurnOutcome;
use App\Agent\Werewolf\SeatTurn;
use App\Agent\Werewolf\SeatTurnRunner;
use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Rules\Game;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\HttpClient\Exception\TransportException;

/** One seat turn over real HTTP MCP with a scripted model (decisions/030): night 1, wolves 1 and 5, seer 3. */
final class SeatTurnRunnerTest extends WerewolfMcpCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createGame('g1', Game::arranged(7, self::ROLES));
    }

    public function testWolfSeesTheDenAndChoosesAVictim(): void
    {
        $turn = $this->play(1, new ToolCallResult([new ToolCall('c1', 'ww_choose_victim', ['target' => 2])]), 'I chose seat 2.');

        self::assertSame(TurnOutcome::Done, $turn->outcome);
        self::assertSame('I chose seat 2.', $turn->finalText);
        self::assertSame(1, $turn->toolCalls);
        self::assertStringContainsString('## wolf-den', $this->userMessage($turn));
        self::assertNotContains(1, $this->game('g1')->game()->pendingSeats());
        self::assertSame([], $this->game('g1')->denials());
    }

    public function testVillagerCallingTheWolfToolIsDeniedAndToldWhatItCanDo(): void
    {
        $turn = $this->play(2, new ToolCallResult([new ToolCall('c1', 'ww_choose_victim', ['target' => 3])]), 'Nothing to do.');

        self::assertSame(TurnOutcome::Done, $turn->outcome);
        self::assertStringNotContainsString('wolf-den', $this->userMessage($turn));
        self::assertStringStartsWith('Error: "ww_choose_victim" is not available', $this->toolResults($turn)[0]);
        self::assertCount(1, $this->game('g1')->denials());
    }

    public function testFourthCallIsRefusedAndTheRunEndsAsBudgetSpent(): void
    {
        $call = new ToolCallResult([new ToolCall('c', 'ww_choose_victim', ['target' => 2])]);

        $turn = $this->play(1, $call, $call, $call, $call, $call, $call);

        self::assertSame(TurnOutcome::BudgetSpent, $turn->outcome);
        self::assertSame(SeatTurnRunner::TOOL_BUDGET, $turn->toolCalls);
        self::assertStringStartsWith('Error: the tool budget for this turn is spent', $this->toolResults($turn)[3]);
    }

    public function testModelTimeoutEndsTheTurnWithoutAction(): void
    {
        $turn = $this->play(1, new TransportException('Idle timeout reached'));

        self::assertSame(TurnOutcome::TimedOut, $turn->outcome);
        self::assertSame(0, $turn->toolCalls);
        self::assertContains(1, $this->game('g1')->game()->pendingSeats());
    }

    private function play(int $seat, ResultInterface|string|\Throwable ...$script): SeatTurn
    {
        $platform = MockPlatformFactory::createPlatform(static function () use (&$script): ResultInterface|string {
            $next = array_shift($script) ?? 'done';

            return $next instanceof \Throwable ? throw $next : $next;
        });
        $runner = new SeatTurnRunner(
            new McpConnector(new KernelBrowserPsr18Client($this->browser), 'http://localhost'),
            self::SECRET,
            \dirname(__DIR__, 3) . '/config/prompts/werewolf/player.md',
        );

        return $runner->play('g1', $seat, new ModelChoice($platform, 'qwen3:8b'));
    }

    private function userMessage(SeatTurn $turn): string
    {
        $user = $turn->messages->getMessages()[1] ?? null;
        self::assertInstanceOf(UserMessage::class, $user);

        return (string) $user->asText();
    }

    /** @return list<string> */
    private function toolResults(SeatTurn $turn): array
    {
        return array_values(array_map(
            static fn(ToolCallMessage $m): string => (string) $m->asText(),
            array_filter($turn->messages->getMessages(), static fn(object $m): bool => $m instanceof ToolCallMessage),
        ));
    }
}
