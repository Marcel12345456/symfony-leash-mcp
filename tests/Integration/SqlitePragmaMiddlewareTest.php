<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Doctrine\SqlitePragmaMiddleware;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlitePragmaMiddlewareTest extends KernelTestCase
{
    public function testEveryConnectionUsesWalAndBusyTimeout(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        self::assertSame('wal', $connection->fetchOne('PRAGMA journal_mode'));
        self::assertSame(SqlitePragmaMiddleware::BUSY_TIMEOUT_MS, $connection->fetchOne('PRAGMA busy_timeout'));
    }
}
