<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * PLAN §7 tests must exist before the video (M8 done-signal 1): every item of docs/test-matrix.md names at least one
 * test, and every named file and method exists. A whole file (no `::method`) counts as a suite reference.
 */
final class TestMatrixTest extends TestCase
{
    private const string MATRIX = __DIR__ . '/../../docs/test-matrix.md';

    public function testEveryPlanItemPointsAtExistingTests(): void
    {
        $ids = [];
        foreach (explode("\n", (string) file_get_contents(self::MATRIX)) as $line) {
            if (1 !== preg_match('/^- \*\*([A-Z]\d+)\*\*/', $line, $id)) {
                continue;
            }
            $ids[] = $id[1];
            preg_match_all('/`(tests\/[^`:]+\.php)(?:::(\w+))?`/', $line, $refs, \PREG_SET_ORDER);
            self::assertNotSame([], $refs, $id[1] . ' names no test');
            foreach ($refs as $ref) {
                $file = __DIR__ . '/../../' . $ref[1];
                self::assertFileExists($file, $id[1]);
                $method = $ref[2] ?? '';
                if ('' !== $method) {
                    self::assertMatchesRegularExpression('/function ' . $method . '\(/', (string) file_get_contents($file), $id[1] . ': ' . $ref[1] . '::' . $method);
                }
            }
        }

        // PLAN §7: 28 library items, 8 Werewolf, 3 Doc Review.
        self::assertSame([...array_map(static fn(int $n): string => 'L' . $n, range(1, 28)), ...array_map(static fn(int $n): string => 'W' . $n, range(1, 8)), 'D1', 'D2', 'D3'], $ids);
    }
}
