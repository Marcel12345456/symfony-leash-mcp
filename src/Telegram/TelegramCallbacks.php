<?php

declare(strict_types=1);

namespace App\Telegram;

use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\ElicitResult;

/**
 * A button press (decisions/034): only from the configured chat, only for an open pending input. It records
 * the answer like the inbox does (the worker resumes the call), confirms the press and removes the keyboard.
 * Re-delivered or repeated presses change nothing.
 */
final readonly class TelegramCallbacks
{
    public function __construct(
        private TelegramBot $bot,
        private PendingInputs $pending,
        private PendingInputAnswers $answers,
    ) {}

    /** @param array<mixed> $callback a raw `CallbackQuery` */
    public function handle(array $callback): TelegramCallbackOutcome
    {
        $id = \is_string($callback['id'] ?? null) ? $callback['id'] : null;
        $message = \is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chat = \is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = \is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $allowed = $this->bot->chatId();
        if (null === $id || null === $allowed || !self::same($chat['id'] ?? null, $allowed) || !self::same($from['id'] ?? null, $allowed)) {
            return TelegramCallbackOutcome::Ignored;
        }

        $data = \is_string($callback['data'] ?? null) ? $callback['data'] : '';
        $pending = 1 === preg_match('/^([0-9a-f]{1,32}):(\d{1,3})$/', $data, $m) ? $this->pending->get($m[1]) : null;
        $question = null === $pending ? null : TelegramQuestion::of($pending);
        $option = null === $question ? null : ($question->options[(int) $m[2]] ?? null);
        if (null === $pending || null === $question || null === $option) {
            $this->bot->answerCallback($id, 'Unknown request.');

            return TelegramCallbackOutcome::Unknown;
        }

        $outcome = TelegramCallbackOutcome::AlreadyAnswered;
        if (PendingInputStatus::Pending === $pending->status) {
            try {
                $this->answers->answer($pending->id, [$question->key => new ElicitResult(ElicitAction::Accept, [$question->field => $option['value']])]);
                $outcome = TelegramCallbackOutcome::Answered;
            } catch (\LogicException) {
            }
        }
        $this->bot->answerCallback($id, TelegramCallbackOutcome::Answered === $outcome ? 'Recorded: ' . $option['label'] : 'Already answered.');
        if (\is_int($message['message_id'] ?? null)) {
            $this->bot->removeKeyboard($allowed, $message['message_id']);
        }

        return $outcome;
    }

    private static function same(mixed $id, string $allowed): bool
    {
        return (\is_int($id) || \is_string($id)) && (string) $id === $allowed;
    }
}
