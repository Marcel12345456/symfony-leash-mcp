<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lib;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Doctrine\DoctrineProcessedInputRequests;
use Leash\HumanInput\Doctrine\ProcessedInputRequest;
use Leash\HumanInput\Idempotency\ProcessedInputRequests;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class DoctrineProcessedInputRequestsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProcessedInputRequests $store;

    protected function setUp(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->entityManager = $em;
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $this->store = new DoctrineProcessedInputRequests($em, new MockClock());
    }

    public function testAppliesOncePerNonceAndReplaysFromTheDatabase(): void
    {
        $applied = 0;
        $apply = static function () use (&$applied): array {
            ++$applied;

            return ['message' => 'Applied.', 'run' => $applied];
        };

        $first = $this->store->once('n1', $apply);
        $this->entityManager->clear();
        $second = $this->store->once('n1', $apply);

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame(['message' => 'Applied.', 'run' => 1], $second->result);
        self::assertSame(['message' => 'Applied.', 'run' => 1], $this->store->find('n1'));
        self::assertNull($this->store->find('n2'));
        self::assertSame(1, $applied);
    }

    public function testFailedApplyRecordsNothing(): void
    {
        try {
            $this->store->once('n1', static fn(): array => throw new \RuntimeException('boom'));
            self::fail('Expected the apply to throw.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame(0, $this->entityManager->getRepository(ProcessedInputRequest::class)->count([]));
    }
}
