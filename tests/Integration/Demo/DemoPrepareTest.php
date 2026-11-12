<?php

declare(strict_types=1);

namespace App\Tests\Integration\Demo;

use App\DocReview\Entity\Document;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Entity\WerewolfGame;
use Leash\HumanInput\Pending\PendingInput;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * M7 done-signal (4): `make demo` tells the story with no model. The test env's Ollama is a closed port, so every
 * answer comes from the committed cassettes: the Werewolf game ends with a winner, the Doc Review documents move
 * forward and the editor's sign-off waits for a human.
 */
final class DemoPrepareTest extends WerewolfMcpCase
{
    public function testWholeDemoReplaysWithoutAModel(): void
    {
        $tester = new CommandTester(new Application($this->browser->getKernel())->find('leash:demo:prepare'));
        $tester->execute(['--model' => 'replay']);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
        $em = $this->entityManager();
        $em->clear();
        self::assertNotNull($em->find(WerewolfGame::class, 'seed-8')?->game()->winner());

        $statuses = [];
        foreach ($em->getRepository(Document::class)->findBy([], ['id' => 'ASC']) as $document) {
            $statuses[$document->getId()] = $document->getStatus();
        }
        self::assertSame(['d1' => 'approved', 'd2' => 'approved', 'd3' => 'approved'], $statuses, 'the recorded story: every document reviewed and approved');
        $signoffs = array_map(static fn(PendingInput $p): string => $p->subjectRef, $em->getRepository(PendingInput::class)->findBy(['tool' => 'doc_publish']));
        sort($signoffs);
        self::assertSame(['doc_review:d1', 'doc_review:d2', 'doc_review:d3'], $signoffs, 'each editor sign-off waits for a human');
    }
}
