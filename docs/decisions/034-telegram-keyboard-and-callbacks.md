# 034 — Telegram: the notifier bridge sends and edits inline keyboards, it does not receive callbacks

- Date: 2026-11-12
- Status: accepted (empty-keyboard edit needs one live smoke test)
- PLAN reference: §3.3 HumanInput, Telegram channel (VERIFY the bridge's inline keyboard / callback support)

## Question

Does symfony/telegram-notifier send inline keyboards with `callback_data` buttons, does it receive `callback_query` updates, and what own code is needed for the rest?

## Answer

Not installed (no `symfony/notifier`, no `symfony/telegram-notifier` in vendor/ or composer.lock); read from the bridge's `8.1` branch on GitHub (requires notifier/http-client `^7.4|^8.0`).

- **Send: yes.** `TelegramOptions::replyMarkup()` (TelegramOptions.php:146-151), `InlineKeyboardMarkup::inlineKeyboard(InlineKeyboardButton[])` adds one row per call (Reply/Markup/InlineKeyboardMarkup.php:33-40), `InlineKeyboardButton('text')->callbackData()` / `->url()` (Button/InlineKeyboardButton.php:29, 69). `SentMessage::getMessageId()` carries Telegram's `message_id` (TelegramTransport.php:146-148).
- **DSN:** `telegram://<botId>:<token>@default?channel=<chatId>` (TelegramTransportFactory.php:30-54); `channel` is the default chat id.
- **Edit / answer: yes, with caveats.** `TelegramOptions::edit(int $messageId)` → `editMessageText` (needs new text); `answerCallbackQuery(id, showAlert, cacheTime)` (TelegramOptions.php:156-176). Edit and answer are exclusive in one message (Transport.php:36-48). The transport forces `parse_mode=MarkdownV2` and escapes some characters (Transport.php:95-106).
- **Receive: no.** No getUpdates, webhook parser or RemoteEvent in the bridge; the Notifier has no inbound channel.
- `callback_data` is limited to 1–64 bytes (Telegram Bot API); the bridge does not check it.

## Decision

- symfony/telegram-notifier only for sending (and answering/editing); transport from `TELEGRAM_DSN`, env-gated: no DSN, no channel, no poller.
- Own `leash:telegram:poll`: HttpClient long-poll `getUpdates` (`allowed_updates: ["callback_query"]`, HttpClient timeout > poll timeout), token parsed from `TELEGRAM_DSN`. Re-delivery is harmless: an answered pending input is a no-op.
- Buttons carry a short opaque `callback_data` (`<pendingId>:<optionIndex>`), never a signed token. On callback: chat and user must equal `TELEGRAM_CHAT_ID`, the pending input must be open, then submit the answer, `answerCallbackQuery` ("Recorded"), remove the keyboard (raw `editMessageReplyMarkup` with an empty keyboard).
- Boolean/enum schema → one button per option; anything else → the signed inbox link as a URL button.

## Consequences

- `composer require symfony/telegram-notifier` (pulls symfony/notifier) + `config/packages/notifier.yaml`; re-check the cited lines in vendor/ after install.
- Tests with a mock HttpClient: wrong chat/user ignored, unknown id ignored, repeated callback idempotent, valid callback resumes the vote.
- Telegram stays second in the cut order (PLAN §6); no library bug, no BUGS.md entry.

## Re-check after install (2026-11-12, symfony/notifier 8.1.11, telegram-notifier 8.1.6)

- Signatures as cited: `TelegramOptions::replyMarkup()` (TelegramOptions.php:146), `parseMode(string)` (:64), `InlineKeyboardMarkup::inlineKeyboard()` appends one row per call (:33), `InlineKeyboardButton::callbackData()` (:69).
- DSN is `telegram://BOTID:TOKEN@default?channel=CHAT`; the factory rebuilds the token as `user:password` and rejects a DSN without both (TelegramTransportFactory.php:46-54).
- MarkdownV2 auto-escape covers only `. ! # + - = | { } >` (TelegramTransport.php:95-106); `_ * [ ] ( ) ~` and backtick stay markup, so model text can fail with "can't parse entities". **Leash sends `parseMode('HTML')` with `htmlspecialchars`**, which skips the auto-escape. Not a bug, a doc gap.
- With messenger installed, `Chatter::send()` dispatches the `ChatMessage` on the bus (handled synchronously, no routing) and returns null: no `SentMessage`, no `message_id`. Not needed: the callback carries `message.message_id`.
- Tests: chatter transport `null://null` dispatches `MessageEvent` with a `NullMessage` wrapper; `getNotifierEvents()` captures it (one queued + one sent event per send).

## Implementation notes

- Gate: `TelegramBot::enabled()` (a `telegram://` DSN with `channel`). The notifier decorates the email notifier, so email still goes out.
- The allowed chat is the DSN `channel`; one env var instead of a separate `TELEGRAM_CHAT_ID`. In a private chat, chat id = user id, so both are checked against it.
- Non-choice inputs get the signed inbox link in the text, not as a URL button: Telegram rejects URL buttons pointing at `localhost`.
