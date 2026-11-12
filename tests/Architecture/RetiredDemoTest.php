<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * M6 acceptance (decisions/022): the Refund Desk is retired (code at tag refund-desk-v1), no reference remains in
 * src/, config/ or tests/ (this file excepted).
 */
final class RetiredDemoTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    public function testNoReferencesToTheRetiredRefundDemoRemain(): void
    {
        $pattern = '/RefundDesk|refund_case/';
        $offenders = [];
        foreach ((new Finder())->files()->in([self::ROOT . '/src', self::ROOT . '/config', self::ROOT . '/tests']) as $file) {
            if (__FILE__ !== $file->getRealPath() && 1 === preg_match($pattern, $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders, 'Refund Desk references remain');
        self::assertDirectoryDoesNotExist(self::ROOT . '/src/RefundDesk');
    }
}
