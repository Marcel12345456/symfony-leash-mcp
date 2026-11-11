<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\Werewolf\TranscriptWriter;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `make werewolf`: the committed cassette (the live qwen3 game, decisions/033) replays to the end of the game with
 * no model and no network (the test env's Ollama is a closed port). A prompt change without a re-record fails here.
 */
final class WerewolfReplayTest extends WerewolfMcpCase
{
    public function testCommittedCassetteReplaysToTheEnd(): void
    {
        $tester = new CommandTester(new Application($this->browser->getKernel())->find('leash:werewolf:play'));
        $tester->execute(['--seed' => '7', '--model' => 'replay']);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
        self::assertMatchesRegularExpression('/Result: (village|wolves|draw)/', $tester->getDisplay());
        $writer = $this->browser->getContainer()->get(TranscriptWriter::class);
        self::assertInstanceOf(TranscriptWriter::class, $writer);
        self::assertMatchesRegularExpression('/\nResult: (village|wolves|draw)\n$/', (string) file_get_contents($writer->path('seed-7')));
    }
}
