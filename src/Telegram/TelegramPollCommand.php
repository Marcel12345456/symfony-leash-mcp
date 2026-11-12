<?php

declare(strict_types=1);

namespace App\Telegram;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** `make demo` runs this when TELEGRAM_DSN is set: Telegram button presses answer pending inputs (decisions/034). */
#[AsCommand('leash:telegram:poll', 'Receive Telegram button presses and answer pending inputs')]
final readonly class TelegramPollCommand
{
    public function __construct(
        private TelegramBot $bot,
        private TelegramPoller $poller,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Handle one batch without waiting, then stop')]
        bool $once = false,
    ): int {
        if (!$this->bot->enabled()) {
            $io->error('TELEGRAM_DSN is not a telegram:// DSN with ?channel=<chat id>.');

            return Command::FAILURE;
        }

        $offset = 0;
        do {
            $offset = $this->poller->poll($offset, $once ? 0 : 30);
        } while (!$once);

        return Command::SUCCESS;
    }
}
