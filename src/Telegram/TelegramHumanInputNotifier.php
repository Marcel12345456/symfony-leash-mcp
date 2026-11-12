<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Inbox\InboxLinks;
use Leash\HumanInput\Pending\HumanInputNotifier;
use Leash\HumanInput\Pending\PendingInput;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Telegram channel (decisions/034), on top of email: a pick list or yes/no becomes one button per option
 * (`callback_data` `<pendingId>:<index>`, read by `leash:telegram:poll`); anything else gets the inbox link.
 */
#[AsDecorator(HumanInputNotifier::class)]
final readonly class TelegramHumanInputNotifier implements HumanInputNotifier
{
    public function __construct(
        #[AutowireDecorated]
        private HumanInputNotifier $inner,
        private ChatterInterface $chatter,
        private TelegramBot $bot,
        private InboxLinks $links,
    ) {}

    public function notify(PendingInput $pending): void
    {
        $this->inner->notify($pending);
        if (!$this->bot->enabled()) {
            return;
        }

        $title = \sprintf('<b>Input needed #%s</b>: %s on %s', $pending->id, self::html($pending->tool), self::html($pending->subjectRef));
        $options = (new TelegramOptions())->parseMode('HTML');
        $question = TelegramQuestion::of($pending);
        if (null === $question) {
            $text = \sprintf("%s\n\nAnswer in the inbox (link expires): %s", $title, self::html($this->links->item($pending->id)));
        } else {
            $text = \sprintf("%s\n\n%s", $title, self::html($question->message));
            $keyboard = new InlineKeyboardMarkup();
            foreach (array_chunk($question->options, 3, true) as $row) {
                $keyboard->inlineKeyboard(array_map(
                    static fn(array $option, int $i): InlineKeyboardButton => (new InlineKeyboardButton($option['label']))->callbackData($pending->id . ':' . $i),
                    $row,
                    array_keys($row),
                ));
            }
            $options->replyMarkup($keyboard);
        }

        $this->chatter->send((new ChatMessage($text, $options))->transport('telegram'));
    }

    private static function html(string $text): string
    {
        return htmlspecialchars($text, \ENT_NOQUOTES);
    }
}
