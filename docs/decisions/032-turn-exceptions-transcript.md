# 032 — Turn runner exceptions and transcript accessors

- Date: 2026-11-10
- Status: accepted (code reading; timeout path pinned by the agent tests)
- PLAN reference: §3.4 Limits / per-turn timeout; follow-up to 029, 030

## Question

Which exceptions must the seat turn runner catch around `Agent::call()->getContent()`, and which message accessors build a transcript?

## Answer

symfony/ai-agent, ai-platform, ai-ollama-platform 0.15.0.

- `Symfony\AI\Agent\Exception\MaxIterationsExceededException` (final) → `Agent\Exception\RuntimeException` → `\RuntimeException`, implements `Agent\Exception\ExceptionInterface` (`ai-agent/src/Exception/*.php`; thrown at `Runner.php:122`). `Platform\Exception\ExceptionInterface` is separate; `Platform\Exception\RuntimeException` is the base of Server/BadRequest/Authentication/RateLimit exceptions.
- Transport errors (timeout, `max_duration`, connection refused) are not wrapped: the raw `Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface` reaches the caller from `RawHttpResult::getData()` (`ai-platform/src/Result/RawHttpResult.php:40-43`), lazily on `getContent()`.
- Ollama 4xx/5xx: `OllamaResultConverter` ignores the status; an `{"error": …}` body becomes `Platform\Exception\RuntimeException('Response does not contain message.')` (`OllamaResultConverter.php:70-72`), the error text is lost.
- `Execution::getContent(): string|iterable|object|null` (`Execution.php:160`); string for a `TextResult`. No final result → `Agent\Exception\RuntimeException` (`:149-153`).
- Messages (`ai-platform/src/Message/`): `MessageBag::getMessages()`; `SystemMessage::getContent(): string|Template`; `UserMessage::asText(): ?string`; `AssistantMessage::asText(): ?string`, `hasToolCalls()`, `getToolCalls()`, `getThinking()`; `ToolCallMessage::getToolCall()`, `asText()`. Factories `Message::forSystem()`, `ofUser()`, `ofAssistant()`, `ofToolCall()`.

## Decision

Catch ladder in the turn runner: `TransportExceptionInterface` → turn timed out; `MaxIterationsExceededException` → budget backstop; `Agent\…\ExceptionInterface|Platform\…\ExceptionInterface` → failed turn. Non-string content → no final text. Any of these ends the turn without action; the moderator's deadline path applies. Transcript from `getMessages()` with the accessors above (tool-call-only assistant messages render their tool calls).

## Consequences

- BUGS.md candidate: Ollama HTTP errors lose their body.
- The scripted fake platform can throw a `TransportExceptionInterface` to test the timeout path.
