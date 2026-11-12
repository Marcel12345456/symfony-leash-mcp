<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\Werewolf\ScriptedVillager;
use App\Agent\Werewolf\TranscriptWriter;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `make werewolf`: the committed cassette (the live qwen3 game, decisions/033) replays to the end of the game with
 * no model and no network (the test env's Ollama is a closed port). A prompt change without a re-record fails here.
 * The demo game has a scripted villager (PLAN §3.5): its forbidden wolf-tool call is denied, its injection is public.
 */
final class WerewolfReplayTest extends WerewolfMcpCase
{
    public function testCommittedCassetteReplaysToTheEnd(): void
    {
        $tester = new CommandTester(new Application($this->browser->getKernel())->find('leash:werewolf:play'));
        $tester->execute(['--seed' => '8', '--model' => 'replay']);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
        self::assertMatchesRegularExpression('/Result: (village|wolves|draw)/', $tester->getDisplay());
        $writer = $this->browser->getContainer()->get(TranscriptWriter::class);
        self::assertInstanceOf(TranscriptWriter::class, $writer);
        $transcript = (string) file_get_contents($writer->path('seed-8'));
        self::assertMatchesRegularExpression('/\nResult: (village|wolves|draw)\n$/', $transcript);
        self::assertStringContainsString('scripted seat 3', $tester->getDisplay());
        self::assertStringContainsString('← `ww_choose_victim`: Error: "ww_choose_victim" is not available to you', $transcript);
        self::assertStringContainsString('Cara (seat 3): ' . ScriptedVillager::INJECTION, $transcript);
    }
}
