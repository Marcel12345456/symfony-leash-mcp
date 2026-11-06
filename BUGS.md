# Bugs found in pre-release libraries

<!-- One entry per bug: package + version, minimal repro, expected vs actual,
     local workaround (class name), upstream issue/PR link once filed. -->

## mcp/sdk 0.9.0 — `Client\Builder::addRequestHandler()` rejects the SDK's own handlers under PHPStan

- Repro: `Client::builder()->addRequestHandler(new ElicitationRequestHandler($cb))` at PHPStan level max.
- Actual: `expects RequestHandlerInterface<mixed>, ElicitationRequestHandler given` (template is invariant).
- Expected: accepted; the template parameter should be covariant (`@template-covariant`) or the parameter typed `RequestHandlerInterface<*>`.
- Workaround: inline `@phpstan-ignore argument.type` with comment in `src/Spike/SpikeMcpCommand.php`.

## Candidates (from code reading, not yet reproduced)

- **symfony/ai-agent 0.15.0 — tool list frozen per run.** `Execution/Runner.php:76` exposes `toolbox->getTools()` once; the comment at `:304` says toolbox tools can change during a run, but `$options['tools']` is never refreshed. Repro idea: toolbox whose `getTools()` changes after the first `execute()`; the second model request still carries the old list. Workaround: one agent run per workflow step (`decisions/006`).
- **mcp/sdk 0.9.0 — `requestState` TTL is global.** `RequestStateCodec` TTL is set once on the builder; no per-state expiry. Workaround: own `expiresAt` in the payload (`decisions/001`).
