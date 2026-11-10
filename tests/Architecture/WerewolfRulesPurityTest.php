<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/** `src/Werewolf/Rules` is pure PHP: no Symfony, no Doctrine, no LLM, no libraries, no other App code. */
final class WerewolfRulesPurityTest extends TestCase
{
    private const string RULES = __DIR__ . '/../../src/Werewolf/Rules';

    /** @return iterable<string, array{string}> */
    public static function forbiddenReferences(): iterable
    {
        yield 'Symfony' => ['/\bSymfony\\\\/'];
        yield 'Doctrine' => ['/\bDoctrine\\\\/'];
        yield 'Leash libraries' => ['/\bLeash\\\\/'];
        yield 'MCP SDK' => ['/\bMcp\\\\/'];
        yield 'App outside the rules' => ['/\bApp\\\\(?!Werewolf\\\\Rules\\b)/'];
    }

    #[DataProvider('forbiddenReferences')]
    public function testRulesDoNotReference(string $pattern): void
    {
        $files = (new Finder())->files()->in(self::RULES)->name('*.php');
        self::assertGreaterThan(0, \count($files));

        $offenders = [];
        foreach ($files as $file) {
            if (1 === preg_match($pattern, $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders, \sprintf('src/Werewolf/Rules must not match %s', $pattern));
    }
}
