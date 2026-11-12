# 028 — Werewolf replay: record with RecordingProvider, replay with an own per-seat platform

- Date: 2026-11-10
- Status: superseded in part by 033 (replay is own code, not RecordingProvider)
- PLAN reference: §3.4 Replay mode (follow-up to 007)

## Question

Can `RecordingProvider`/`Cassette` record one live Ollama game and replay it offline, and is that better than an own replay platform? How do scripted test platforms work?

## Answer

symfony/ai-platform 0.15.0 (source reading; signature behaviour derived, not run).

- `RecordingProvider` (final) decorates a `ProviderInterface`, not a platform: `(ProviderInterface $provider, Cassette $cassette, ?bool $record = null, ?\Closure $signature = null)`, wrap as `new Platform([$recording])` (`Test/Recording/RecordingProvider.php:39, 57-62`; `Platform.php:36-40`). Mode `$record ?? !$cassette->exists()` (`:63`).
- `Cassette(string $path)`: JSON `{"interactions":[{model, signature, result}]}`, rewritten after every record, FIFO per signature, miss/exhausted → `RuntimeException` "…has no recorded interaction for signature…" (`Cassette.php:23, 43, 66-92, 116-139`).
- Shipped in the package, no PHPUnit dependency in `Test/Recording` (usable from a console command).
- **Default signature** `xxh128(json_encode([model, $input, $options]))` (`RecordingProvider.php:120-131`). `MessageBag`, messages and `Tool` have only private properties and no `jsonSerialize` → they encode as `{}`. The signature is effectively content-blind: replay is FIFO per (model, options shape), and a prompt change serves wrong answers silently instead of missing. Message UUIDs therefore do not matter either.
- `ResultSerializer` round-trips text, toolcall (id, name, arguments), token usage; other metadata types throw at record time (`ResultSerializer.php:34, 100-269`).
- Scripted fakes: `MockPlatformFactory::createPlatform(\Closure|string|array $responses, …)`; closure `(Model $model, array|string|object $input, array $options): ResultInterface|string`; tool turn `new ToolCallResult(new ToolCall('id1', 'ww_vote', ['target' => 2]))`; text turn = string (`MockPlatformFactory.php:38-72`, `ScriptedResponse.php:22-55`, `Result/ToolCall.php:24-29`).

## Decision

- Tests: `MockPlatformFactory` with a stateful closure.
- Recording run: `RecordingProvider` with `record: true` around the Ollama provider, writing the cassette.
- Replay (`make werewolf`, tests): own small `ReplayPlatform` in `src/Agent` built on `MockPlatformFactory`, reading `interactions[].result` via `ResultSerializer`, **FIFO per seat** (seat key from a custom recording signature closure, e.g. `seat:3`), failing loudly on an empty queue; a prompt hash in the cassette detects drift.

## Consequences

- Never rely on the default signature. Pin its content-blindness with a short test (two different bags → same signature); if confirmed, BUGS.md entry.
- Prompt edits after recording require a re-record; the prompt hash makes that explicit.
