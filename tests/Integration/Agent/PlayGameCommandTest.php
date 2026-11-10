<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\Werewolf\TranscriptWriter;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** `leash:werewolf:play` on the test env's unreachable Ollama: every turn fails, the deadline path alone moves the game. */
final class PlayGameCommandTest extends WerewolfMcpCase
{
    public function testUnfinishedGameFailsAndClosesTheTranscript(): void
    {
        $tester = $this->play(['--seed' => '3', '--model' => 'ollama', '--max-steps' => '2']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Game seed-3', $tester->getDisplay());
        self::assertStringContainsString('Step 2: played', $tester->getDisplay());
        $transcript = (string) file_get_contents($this->transcripts()->path('seed-3'));
        self::assertMatchesRegularExpression('/Result: unfinished \(day \d+, [a-z_]+\)\n$/', $transcript);
    }

    public function testRerunReplacesTheGame(): void
    {
        $this->play(['--game' => 'g9', '--model' => 'ollama', '--max-steps' => '2']);
        $this->play(['--game' => 'g9', '--model' => 'ollama', '--max-steps' => '1']);

        self::assertSame(1, $this->game('g9')->game()->day());
        self::assertSame(1, substr_count((string) file_get_contents($this->transcripts()->path('g9')), 'Result:'));
    }

    /** @param array<string, string> $options */
    private function play(array $options): CommandTester
    {
        $kernel = $this->browser->getKernel();
        $tester = new CommandTester(new Application($kernel)->find('leash:werewolf:play'));
        $tester->execute($options);

        return $tester;
    }

    private function transcripts(): TranscriptWriter
    {
        $writer = $this->browser->getContainer()->get(TranscriptWriter::class);
        self::assertInstanceOf(TranscriptWriter::class, $writer);

        return $writer;
    }
}
