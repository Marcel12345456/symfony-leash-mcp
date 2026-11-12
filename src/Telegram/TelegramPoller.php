<?php

declare(strict_types=1);

namespace App\Telegram;

use Psr\Log\LoggerInterface;

/** One `getUpdates` batch: every button press goes to `TelegramCallbacks`; returns the next offset. */
final readonly class TelegramPoller
{
    public function __construct(
        private TelegramBot $bot,
        private TelegramCallbacks $callbacks,
        private LoggerInterface $logger,
    ) {}

    public function poll(int $offset, int $timeout): int
    {
        foreach ($this->bot->updates($offset, $timeout) as $update) {
            $offset = \is_int($update['update_id'] ?? null) ? max($offset, $update['update_id'] + 1) : $offset;
            if (\is_array($update['callback_query'] ?? null)) {
                $outcome = $this->callbacks->handle($update['callback_query']);
                $this->logger->info('Telegram button press: {outcome}', ['outcome' => $outcome->value]);
            }
        }

        return $offset;
    }
}
