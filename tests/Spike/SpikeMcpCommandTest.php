<?php

declare(strict_types=1);

namespace App\Tests\Spike;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Phase 0 Proof 1 runs in make qa, so CI keeps proving the MCP round-trip over real HTTP. */
final class SpikeMcpCommandTest extends KernelTestCase
{
    public function testProofOneRoundTripsOverStreamableHttp(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('spike:mcp'));

        $exitCode = $tester->execute(['--port' => '8124']);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertStringContainsString('tools(closed): []', $tester->getDisplay());
        self::assertStringContainsString('tools(open): [spike_ask]', $tester->getDisplay());
        self::assertStringContainsString('elicitation asked: Approve: refund 1200 cents?', $tester->getDisplay());
        self::assertStringContainsString('tool result: approved: refund 1200 cents', $tester->getDisplay());
    }
}
