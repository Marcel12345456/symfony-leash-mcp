<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Web server, messenger worker and scheduler write to the same SQLite file.
 * WAL lets readers run beside one writer; busy_timeout makes writers wait instead of
 * failing with "database is locked".
 */
#[AsMiddleware]
final readonly class SqlitePragmaMiddleware implements Middleware
{
    public const int BUSY_TIMEOUT_MS = 5000;

    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);

                if (\in_array($params['driver'] ?? null, ['pdo_sqlite', 'sqlite3'], true)) {
                    $connection->exec('PRAGMA busy_timeout = ' . SqlitePragmaMiddleware::BUSY_TIMEOUT_MS);
                    $connection->exec('PRAGMA journal_mode = WAL');
                }

                return $connection;
            }
        };
    }
}
