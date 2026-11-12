<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M8 done-signal (2): the README follows PLAN §9 and does not rot. Its sections are there, every `make <target>` it
 * names exists, every relative link resolves, and both library READMEs exist.
 */
final class ReadmeTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    public function testHasThePlanSections(): void
    {
        preg_match_all('/^## (.+)$/m', self::read('README.md'), $headings);

        self::assertSame([
            'Quick start (no model needed)',
            'What you are seeing',
            'Try it from an MCP client',
            'How it works',
            'Same libraries, zero game code: Doc Review',
            'Using the libraries in your own app',
            'Related work',
            'Bugs found upstream',
            'Commands',
            'Telegram (optional)',
            'Versions and limitations',
            'License',
        ], $headings[1]);
    }

    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        yield 'README' => ['README.md'];
        yield 'WorkflowMcp' => ['lib/WorkflowMcp/README.md'];
        yield 'HumanInput' => ['lib/HumanInput/README.md'];
    }

    #[DataProvider('documents')]
    public function testEveryMakeTargetExists(string $document): void
    {
        preg_match_all('/^([a-z][a-z-]*):/m', self::read('Makefile'), $targets);
        preg_match_all('/\bmake ([a-z][a-z-]*)/', self::read($document), $named);

        self::assertSame([], array_values(array_diff(array_unique($named[1]), $targets[1])), $document . ' names make targets that do not exist');
    }

    #[DataProvider('documents')]
    public function testRelativeLinksResolve(string $document): void
    {
        preg_match_all('/\]\((?!https?:|#)([^)#\s]+)/', self::read($document), $links);
        $base = \dirname(self::ROOT . '/' . $document);
        $broken = array_values(array_filter($links[1], static fn(string $link): bool => !file_exists($base . '/' . $link)));

        self::assertSame([], $broken, $document . ' has broken relative links');
    }

    private static function read(string $file): string
    {
        $content = file_get_contents(self::ROOT . '/' . $file);
        self::assertIsString($content, $file);

        return $content;
    }
}
