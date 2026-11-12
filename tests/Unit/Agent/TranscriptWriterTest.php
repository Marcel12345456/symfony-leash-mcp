<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\TurnOutcome;
use App\Agent\Werewolf\SeatTurn;
use App\Agent\Werewolf\TranscriptWriter;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\Role;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class TranscriptWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/leash-transcript-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testTurnShowsWhatTheSeatSawItsCallsAndItsAnswer(): void
    {
        $call = new ToolCall('c1', 'ww_choose_victim', ['target' => 2]);
        $messages = new MessageBag(
            Message::forSystem('system prompt'),
            Message::ofUser('You are seat 1. ## wolf-den'),
            Message::ofAssistant(new ToolCallResult([$call])),
            Message::ofToolCall($call, 'Seat 2 is your choice.'),
        );
        $writer = new TranscriptWriter($this->directory);

        $writer->turn('g1', 1, Phase::Night, new SeatTurn(1, $messages, 'I chose seat 2.', 1, TurnOutcome::Done));
        $text = $this->read($writer);

        self::assertStringContainsString('## Day 1, night: seat 1 (done, 1 tool call)', $text);
        self::assertStringContainsString('You are seat 1. ## wolf-den', $text);
        self::assertStringContainsString('- → `ww_choose_victim` {"target":2}', $text);
        self::assertStringContainsString('- ← `ww_choose_victim`: Seat 2 is your choice.', $text);
        self::assertStringContainsString('> I chose seat 2.', $text);
        self::assertStringNotContainsString('system prompt', $text);
    }

    public function testFailedTurnNamesWhyItEnded(): void
    {
        $writer = new TranscriptWriter($this->directory);

        $writer->turn('g1', 1, Phase::Night, new SeatTurn(3, new MessageBag(), null, 0, TurnOutcome::TimedOut, 'Idle timeout reached'));

        self::assertStringContainsString("seat 3 (timed_out, 0 tool calls)\n\n**Turn ended:** Idle timeout reached", $this->read($writer));
    }

    public function testLastLineNamesTheResult(): void
    {
        $writer = new TranscriptWriter($this->directory);
        $writer->turn('g1', 1, Phase::Night, new SeatTurn(1, new MessageBag(), 'ok', 0, TurnOutcome::Done));

        $writer->finish('g1', self::game());
        $lines = explode("\n", rtrim($this->read($writer), "\n"));

        self::assertSame('Result: unfinished (day 1, night)', end($lines));
        self::assertContains('## Public log', $lines);
    }

    private static function game(): Game
    {
        return Game::arranged(7, ['Anna' => Role::Wolf, 'Ben' => Role::Villager, 'Cara' => Role::Seer, 'Dan' => Role::Villager, 'Eve' => Role::Wolf, 'Finn' => Role::Villager]);
    }

    private function read(TranscriptWriter $writer): string
    {
        return (string) file_get_contents($writer->path('g1'));
    }
}
