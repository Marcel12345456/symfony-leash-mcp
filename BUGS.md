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

## Candidates (from code reading, not yet reproduced)

- **symfony/ai-agent 0.15.0 — tool list frozen per run.** `Execution/Runner.php:76` exposes `toolbox->getTools()` once; the comment at `:304` says toolbox tools can change during a run, but `$options['tools']` is never refreshed. Repro idea: toolbox whose `getTools()` changes after the first `execute()`; the second model request still carries the old list. Workaround: one agent run per workflow step (`decisions/006`).
- **mcp/sdk 0.9.0 — `requestState` TTL is global.** `RequestStateCodec` TTL is set once on the builder; no per-state expiry. Workaround: own `expiresAt` in the payload (`decisions/001`).
