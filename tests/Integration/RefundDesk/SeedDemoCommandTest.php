<?php

declare(strict_types=1);

namespace App\Tests\Integration\RefundDesk;

use App\RefundDesk\Entity\Refund;
use App\RefundDesk\Entity\RefundCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoCommandTest extends KernelTestCase
{
    public function testSeedIsIdempotentAndResetsCases(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $seed = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('leash:demo:seed'));
        $seed->execute([]);
        $c1 = $em->find(RefundCase::class, 'C1');
        self::assertInstanceOf(RefundCase::class, $c1);
        $c1->setStatus('closed');
        $em->flush();

        $seed->execute([]);
        $seed->assertCommandIsSuccessful();
        $em->clear();

        $cases = $em->getRepository(RefundCase::class)->findAll();
        self::assertCount(9, $cases);
        $statuses = array_combine(array_map(static fn(RefundCase $c): string => $c->getId(), $cases), array_map(static fn(RefundCase $c): string => $c->getStatus(), $cases));
        ksort($statuses);
        self::assertSame(['C1' => 'received', 'C2' => 'received', 'C3' => 'received', 'C4' => 'received', 'C5' => 'received', 'C6' => 'received', 'C7' => 'received', 'C8' => 'received', 'H5' => 'closed'], $statuses);
        self::assertCount(1, $em->getRepository(Refund::class)->findAll(), 'only the H5 history refund');
        self::assertNull($em->find(RefundCase::class, 'C8')?->getOrder(), 'C8 matches no order');
        self::assertStringContainsString('ignore all previous instructions', $em->find(RefundCase::class, 'C3')?->getCustomerMessage() ?? '');
    }
}
