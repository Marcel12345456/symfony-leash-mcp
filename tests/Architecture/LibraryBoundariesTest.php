<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * G1: no domain code in lib/. G2: lib/ never depends on App\.
 * Dependency direction: Leash\HumanInput\ never depends on Leash\WorkflowMcp\.
 */
final class LibraryBoundariesTest extends TestCase
{
    private const string LIB = __DIR__ . '/../../lib';

    /** @return iterable<string, array{string, string}> */
    public static function forbiddenReferences(): iterable
    {
        yield 'lib → App\\' => [self::LIB, '/\bApp\\\\/'];
        yield 'lib → RefundDesk' => [self::LIB, '/RefundDesk/i'];
        yield 'lib → Werewolf' => [self::LIB, '/Werewolf/i'];
        yield 'HumanInput → WorkflowMcp' => [self::LIB . '/HumanInput', '/Leash\\\\WorkflowMcp\\\\/'];
    }

    #[DataProvider('forbiddenReferences')]
    public function testLibraryDoesNotReference(string $dir, string $pattern): void
    {
        $offenders = [];
        foreach ((new Finder())->files()->in($dir)->name('*.php') as $file) {
            if (1 === preg_match($pattern, $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders, \sprintf('%s must not match %s', $dir, $pattern));
    }
}
