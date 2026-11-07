<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput;

use Leash\HumanInput\Idempotency\InMemoryProcessedInputRequests;
use PHPUnit\Framework\TestCase;

final class InMemoryProcessedInputRequestsTest extends TestCase
{
    public function testAppliesOncePerNonceAndReplaysTheStoredResult(): void
    {
        $store = new InMemoryProcessedInputRequests();
        $applied = 0;
        $apply = static function () use (&$applied): array {
            ++$applied;

            return ['state' => 'published', 'run' => $applied];
        };

        $first = $store->once('n1', $apply);
        $second = $store->once('n1', $apply);
        $other = $store->once('n2', $apply);

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame(['state' => 'published', 'run' => 1], $second->result);
        self::assertSame(['state' => 'published', 'run' => 2], $other->result);
        self::assertSame(2, $applied);
    }
}
