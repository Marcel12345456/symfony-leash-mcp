<?php

declare(strict_types=1);

namespace App\Telegram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The raw Bot API calls the Notifier bridge does not offer (decisions/034): receive button presses
 * (`getUpdates`), answer them, and remove the keyboard. Token and chat come from `TELEGRAM_DSN`.
 */
final readonly class TelegramBot
{
    private ?string $token;
    private ?string $chatId;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(TELEGRAM_DSN)%')]
        string $dsn,
    ) {
        $parts = parse_url($dsn);
        parse_str($parts['query'] ?? '', $query);
        $configured = 'telegram' === ($parts['scheme'] ?? null) && isset($parts['user']) && \is_string($query['channel'] ?? null);
        $this->token = $configured ? urldecode($parts['user']) . (isset($parts['pass']) ? ':' . urldecode($parts['pass']) : '') : null;
        $this->chatId = $configured ? $query['channel'] : null;
    }

    /** No `telegram://` DSN with a channel: no messages, no poller. */
    public function enabled(): bool
    {
        return null !== $this->token;
    }

    /** The one chat (and, in a private chat, the one user) whose button presses count. */
    public function chatId(): ?string
    {
        return $this->chatId;
    }

    /**
     * Long-polls button presses.
     *
     * @return list<array<mixed>> raw `Update` objects
     */
    public function updates(int $offset, int $timeout): array
    {
        $result = $this->call('getUpdates', ['offset' => $offset, 'timeout' => $timeout, 'allowed_updates' => ['callback_query']], $timeout + 10);

        return array_values(array_filter($result, \is_array(...)));
    }

    public function answerCallback(string $callbackId, string $text): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    public function removeKeyboard(string $chatId, int $messageId): void
    {
        $this->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => ['inline_keyboard' => []]]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function call(string $method, array $body, float $timeout = 10): array
    {
        if (null === $this->token) {
            throw new \LogicException('Telegram is not configured (TELEGRAM_DSN).');
        }
        $response = $this->http->request('POST', \sprintf('https://api.telegram.org/bot%s/%s', $this->token, $method), ['json' => $body, 'timeout' => $timeout]);
        $data = $response->toArray(false);
        if (true !== ($data['ok'] ?? null)) {
            throw new \RuntimeException(\sprintf('Telegram %s failed: %s', $method, \is_string($data['description'] ?? null) ? $data['description'] : 'no description'));
        }

        return \is_array($data['result'] ?? null) ? $data['result'] : [];
    }
}
