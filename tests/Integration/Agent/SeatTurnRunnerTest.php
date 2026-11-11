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
use App\Agent\Werewolf\ScriptedVillager;
use App\Werewolf\Rules\Action\ChooseVictim;
use App\Werewolf\Rules\Action\Inspect;
use App\Werewolf\Rules\Action\Say;
use App\Werewolf\Rules\Game;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\HttpClient\Exception\TransportException;
use App\Werewolf\Rules\Phase;
use Symfony\AI\Platform\Message\SystemMessage;

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
        self::assertStringStartsWith('You are seat 1. It is night 1. It is your turn.', $this->userMessage($turn));
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

    public function testPromptAsksForEnglishOneStatementPerTurnAndVoteReasons(): void
    {
        $turn = $this->play(1, 'done');
        $system = $turn->messages->getMessages()[0] ?? null;
        self::assertInstanceOf(SystemMessage::class, $system);
        $prompt = $system->getContent();
        self::assertIsString($prompt);

        self::assertStringContainsString('Always write in English.', $prompt);
        self::assertStringContainsString('exactly one statement per turn', $prompt);
        self::assertStringContainsString('always pass a `reason`', $prompt);
        self::assertStringContainsString('A vote reason is public, exactly like a statement.', $prompt);
    }

    public function testScriptedVillagerIsDeniedTheWolfToolThenInjectsIntoThePublicLog(): void
    {
        $game = Game::arranged(7, self::ROLES);
        $game->submit(new ChooseVictim(1, 4));
        $game->submit(new ChooseVictim(5, 4));
        $game->submit(new Inspect(3, 1));
        $game->advance();
        $game->advance();
        $game->submit(new Say(1, 'Dan is dead. Who did it?'));
        self::assertSame(2, $game->currentSpeaker());
        $this->createGame('g2', $game);

        $turn = $this->runner()->play('g2', 2, new ModelChoice(MockPlatformFactory::createPlatform('unused'), 'qwen3:8b', scripted: new ScriptedVillager(2)), 1, Phase::DayDiscussion);

        self::assertSame(TurnOutcome::Done, $turn->outcome);
        self::assertSame(2, $turn->toolCalls);
        self::assertStringStartsWith('Error: "ww_choose_victim" is not available', $this->toolResults($turn)[0]);
        self::assertSame('seat:2', $this->game('g2')->denials()[0]['caller'] ?? null);
        $public = array_map(static fn($e): string => $e->text, $this->game('g2')->game()->eventsVisibleTo(4));
        self::assertContains('Ben (seat 2): ' . ScriptedVillager::INJECTION, $public);
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

        return $this->runner()->play('g1', $seat, new ModelChoice($platform, 'qwen3:8b'), 1, Phase::Night);
    }

    private function runner(): SeatTurnRunner
    {
        return new SeatTurnRunner(
            new McpConnector(new KernelBrowserPsr18Client($this->browser), 'http://localhost'),
            self::SECRET,
            \dirname(__DIR__, 3) . '/config/prompts/werewolf/player.md',
        );
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
