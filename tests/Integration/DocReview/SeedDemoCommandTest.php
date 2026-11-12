<?php

declare(strict_types=1);

namespace App\Tests\Integration\DocReview;

use App\DocReview\Entity\DocEvent;
use App\DocReview\Entity\DocEventType;
use App\DocReview\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoCommandTest extends KernelTestCase
{
    public function testSeedIsIdempotentAndLeavesExistingDocumentsAlone(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $seed = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('leash:demo:seed'));
        $seed->execute([]);
        $seed->assertCommandIsSuccessful();
        $seed->execute([]);
        $seed->assertCommandIsSuccessful();
        $em->clear();

        $documents = $em->getRepository(Document::class)->findAll();
        $statuses = array_combine(array_map(static fn(Document $d): string => $d->getId(), $documents), array_map(static fn(Document $d): string => $d->getStatus(), $documents));
        ksort($statuses);
        self::assertSame(['d1' => 'drafting', 'd2' => 'drafting', 'd3' => 'drafting'], $statuses);
        $d2 = $em->find(Document::class, 'd2')?->brief() ?? '';
        self::assertStringContainsString('Node 18', $d2);
        self::assertStringNotContainsStringIgnoringCase('upgrade notes', $d2, 'the rubric, not the brief, asks for upgrade notes');
    }

    public function testResetStartsOver(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $seed = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('leash:demo:seed'));
        $seed->execute([]);
        $d1 = $em->find(Document::class, 'd1');
        self::assertNotNull($d1);
        $d1->setStatus('published');
        $em->persist(new DocEvent('d1', DocEventType::ToolCalled, 'agent:author', ['tool' => 'doc_submit_draft']));
        $em->flush();
        $em->clear();

        $seed->execute(['--reset' => true]);
        $seed->assertCommandIsSuccessful();
        $em->clear();

        self::assertSame('drafting', $em->find(Document::class, 'd1')?->getStatus());
        self::assertSame([], $em->getRepository(DocEvent::class)->findAll());
    }
}
