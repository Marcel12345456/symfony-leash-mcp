# Bugs found in pre-release libraries

<!-- One entry per bug: package + version, minimal repro, expected vs actual,
     local workaround (class name), upstream issue/PR link once filed. -->

## mcp/sdk 0.9.0 — `Client\Builder::addRequestHandler()` rejects the SDK's own handlers under PHPStan

- Repro: `Client::builder()->addRequestHandler(new ElicitationRequestHandler($cb))` at PHPStan level max.
- Actual: `expects RequestHandlerInterface<mixed>, ElicitationRequestHandler given` (template is invariant).
- Expected: accepted; the template parameter should be covariant (`@template-covariant`) or the parameter typed `RequestHandlerInterface<*>`.
- Workaround: inline `@phpstan-ignore argument.type` with comment in `src/Spike/SpikeMcpCommand.php`.

## symfony/ai-ollama-platform 0.15.0 — no total token count

- Repro: agent call on `ai.platform.ollama` (`qwen2.5:7b`); read `$result->getMetadata()->get('token_usage')`.
- Actual: `TokenUsageAggregation` with prompt 425, completion 59, `getTotalTokens()` = null.
- Expected: total = prompt + completion (Ollama returns `prompt_eval_count` and `eval_count`).
- Workaround: compute prompt + completion ourselves (`src/Spike/SpikeLlmToolCommand.php`; later SpendGuard).

## symfony/ai-bundle 0.15.0 — `ai.platform.ollama` ignores the framework HTTP client config

- Repro: `ai.platform.ollama.http_client: <scoped client with timeout: 3>` (or `framework.http_client.default_options.timeout: 3`); call a slow model (`qwen3`, long prompt) through `ai.platform.ollama`.
- Actual: fails with `Idle timeout reached` after 60.2 s both times; with `php -d default_socket_timeout=3` it fails after 3.2 s. The platform uses an HTTP client that takes its timeout from `default_socket_timeout`, not the configured client.
- Expected: the configured `http_client` (and its timeout) is used.
- Workaround: `default_socket_timeout=300` in `docker/php/leash.ini`.

## symfony/ai-platform 0.15.0 — `JsonSchema\Factory` emits invalid/unusable schema for nullable and constrained properties

- Repro: `(new Factory())->buildProperties(ReviewInput::class)` with `public ?Verdict $previous = null`, `public ?string $note`, `#[Assert\Positive] public int $score` (`tests/Unit/Lib/WorkflowMcp/SchemaFactoryTest::testPlatformFactoryRawOutputNeedsTheNormalizer`).
- Actual: `previous` → `{type: ["string","null"], enum: ["keep","drop"]}` (null fails the enum); `note` is in `required` although nullable; `score` gets draft-4 boolean `exclusiveMinimum: true`; NotBlank emits OpenAPI `nullable`.
- Expected: `enum` includes `null` for nullable enums; JSON Schema 2020-12 numeric `exclusiveMinimum`; no OpenAPI keywords. (Required-for-nullable is debatable; MCP clients then must send `null`.)
- Workaround: `Leash\WorkflowMcp\Schema\SchemaFactory::normalize()` (decisions/013, 015).

## Candidates (from code reading, not yet reproduced)

- **symfony/ai-mcp-tool 0.15.0 — `McpToolbox` tool-name prefix cannot be disabled.** `McpToolbox.php:84` falls back to `<toolset name>_` when the prefix is empty; the model never sees the server's own names. JSON-RPC errors from `callTool` become a generic "An error occurred while executing tool" under `FaultTolerantToolbox`, losing the server's actionable message. Workaround: `Leash\WorkflowMcp\Client\McpClientToolbox` (`decisions/027`).
- **symfony/ai-platform 0.15.0 — `RecordingProvider` default signature ignores message content.** `json_encode([$model, $input, $options])` encodes `MessageBag`/`Tool` (private props, no `JsonSerializable`) as `{}`, so replay is FIFO per model and a changed prompt is served the old answer. To pin with a test (`decisions/028`). Workaround: own replay platform.

- **symfony/ai-agent 0.15.0 — tool list frozen per run.** `Execution/Runner.php:76` exposes `toolbox->getTools()` once; the comment at `:304` says toolbox tools can change during a run, but `$options['tools']` is never refreshed. Repro idea: toolbox whose `getTools()` changes after the first `execute()`; the second model request still carries the old list. Workaround: one agent run per workflow step (`decisions/006`).
- **mcp/sdk 0.9.0 — `requestState` TTL is global.** `RequestStateCodec` TTL is set once on the builder; no per-state expiry. Workaround: own `expiresAt` in the payload (`decisions/001`).

## mcp/sdk 0.9.0 — `Client::callTool()` cannot defer `input_required` or send a later retry

- Repro: `$client->callTool('x', [...])` against a tool that returns `InputRequiredResult`, with a client that wants to park the request for a human answering hours later.
- Actual: `Client\Protocol::request()` always auto-resolves rounds through elicitation handlers (max 10, `Client/Protocol.php:555-620`); `requestState` is never exposed, and `callTool()` has no parameter for `inputResponses`/`requestState`. Throwing from the callback yields a message-only `RuntimeException`.
- Expected: an option to return the raw `InputRequiredResult`, and a way to send a retry with `inputResponses` + `requestState` (MRTR is designed for exactly this deferral).
- Workaround: raw JSON-RPC client `Leash\HumanInput\Client\RawMcpClient` (decisions/017, 021).
