# 033 — Replay: own per-seat ReplayProvider over ResultSerializer; RecordingProvider facts

- Date: 2026-11-10
- Status: accepted (default-signature collision pinned by `ReplayPlatformTest`, BUGS.md)
- PLAN reference: §3.4 Replay mode (follow-up to 028)

## Question

How do we wire Ollama into record/replay per seat, and what do RecordingProvider, Cassette and ResultSerializer offer?

## Answer

symfony/ai-platform 0.15.0, source reading (paths under `vendor/symfony/`).

- Provider: `Bridge\Ollama\Factory::createProvider(?string $endpoint, ?string $apiKey, ?HttpClientInterface $httpClient, …): ProviderInterface` (ai-ollama-platform/Factory.php:34-41). `createPlatform` is `new Platform([createProvider(...)], new CatalogBasedModelRouter(), $dispatcher)` (:66-79), so `new Platform([$provider])` is equivalent (ai-platform/src/Platform.php:35-39). The given HttpClient (and its timeout) is kept (Factory.php:42-51).
- `RecordingProvider(ProviderInterface, Cassette, ?bool $record = null, ?\Closure $signature = null)`; `$record` defaults to `!$cassette->exists()` (Test/Recording/RecordingProvider.php:58-66). Signature closure: `(string|Model $model, array|string|object $input, array $options): string`, called on every `invoke()`. `$input` is the raw `MessageBag`, which the Agent mutates across tool rounds (ai-agent/src/Execution/Runner.php:84, 129-130).
- `Cassette` has no read-all API (`exists()`, `record()`, `match()` only). File: `{"interactions":[{model, signature, result}]}`.
- `ResultSerializer::toArray(ResultInterface): array` / `::fromArray(array): ResultInterface` (static, Test/Recording/ResultSerializer.php:63, 78); text `{type: text, content, …}`, toolcall `{type: toolcall, tool_calls: [{id, name, arguments}]}`, optional `metadata`. Ollama non-streaming metadata (`finish_reason`, `token_usage`) serializes; streaming deltas (thinking) throw, so stay non-streaming.
- Replay result: `new DeferredResult(new PlainConverter($result), $raw, $options)` (RecordingProvider.php:99-105).
- One recorded entry = one model request; the Agent's Runner calls the platform once per tool round (Runner.php:84, 113, 123), so a recorded `ToolCallResult` replays through `Agent` + toolbox.
- `ProviderInterface`: `getName()`, `supports(string|Model)`, `invoke(string|Model, array|string|object, array $options = []): DeferredResult`, `getModelCatalog()`.
- Default signature `hash('xxh128', json_encode([model, $input, $options]))` (RecordingProvider.php:117-124); `MessageBag` encodes as `{}`, so all Agent calls collide (confirms 028).

## Decision

- Own `App\Agent\Replay` code, no `RecordingProvider`/`Cassette`: a recording decorator around the Ollama provider (`Factory::createProvider`) appends `{seat, result: ResultSerializer::toArray(...)}` to our cassette JSON; `ReplayProvider` keeps a FIFO queue per seat from that file and returns `DeferredResult(PlainConverter(ResultSerializer::fromArray(...)))`; empty queue → loud exception. The seat is read from the turn message (`You are seat N`) at call time.
- Cassette stores a prompt hash; replay refuses a cassette recorded with a different `player.md`.
- Recording mode is always explicit (`--record`), never inferred from file existence.

## Consequences

- Test: default signature equal for two different bags (pins the bug; BUGS.md entry).
- Test: per-seat FIFO order; a `ToolCallResult` replays through `Agent` + toolbox.
