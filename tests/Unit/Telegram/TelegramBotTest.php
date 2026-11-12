<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram;

use App\Telegram\TelegramBot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/** Telegram is env-gated (decisions/034): only a `telegram://BOTID:TOKEN@default?channel=CHAT` DSN enables it. */
final class TelegramBotTest extends TestCase
{
    public function testTelegramDsnEnablesTheBot(): void
    {
        $bot = new TelegramBot(new MockHttpClient(), 'telegram://123:abc@default?channel=4242');

        self::assertTrue($bot->enabled());
        self::assertSame('4242', $bot->chatId());
    }

    /** @return iterable<string, array{string}> */
    public static function disabled(): iterable
    {
        yield 'null transport (.env default)' => ['null://null'];
        yield 'empty' => [''];
        yield 'no channel' => ['telegram://123:abc@default'];
    }

    #[DataProvider('disabled')]
    public function testAnythingElseDisablesIt(string $dsn): void
    {
        $bot = new TelegramBot(new MockHttpClient(), $dsn);

        self::assertFalse($bot->enabled());
        self::assertNull($bot->chatId());
        $this->expectException(\LogicException::class);
        $bot->updates(0, 0);
    }
}
