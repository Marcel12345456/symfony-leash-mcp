<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Werewolf\Security\WerewolfCallers;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** `leash:tokens` prints exactly the token the MCP firewall accepts for a caller (README: try it from an MCP client). */
final class TokensCommandTest extends KernelTestCase
{
    public function testPrintsTheTokenOfOneCaller(): void
    {
        $tester = $this->tester();
        $tester->execute(['caller' => 'seat:1']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(WerewolfCallers::token('test-werewolf-secret', 'seat:1') . "\n", $tester->getDisplay());
    }

    public function testListsEveryCaller(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        foreach ([...WerewolfCallers::all(), 'agent:author', 'agent:reviewer'] as $caller) {
            self::assertStringContainsString($caller, $tester->getDisplay());
        }
    }

    public function testUnknownCallerFailsWithTheKnownOnes(): void
    {
        $tester = $this->tester();
        $tester->execute(['caller' => 'seat:9']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('seat:1', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('leash:tokens'));
    }
}
