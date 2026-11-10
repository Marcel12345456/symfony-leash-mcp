# 030 — Scripted fake platform for agent tests

- Date: 2026-11-10
- Status: proposed (pinned once the first agent test uses it)
- PLAN reference: §3.4 Platform, §7 agent tests; follow-up to 029

## Question

How do we script a fake platform so agent tests return tool calls, then text, and know which seat/turn they serve?

## Answer

symfony/ai-platform 0.15.0, symfony/ai-agent 0.15.0.

- **Shipped double:** `Symfony\AI\Platform\Test\MockPlatformFactory::createPlatform(\Closure|string|array $responses, ModelCatalogInterface $catalog = new FallbackModelCatalog(), ?Contract $contract = null, ?EventDispatcherInterface $ed = null, string $name = 'mock', ?ModelRouterInterface $router = null): Platform` (`Test/MockPlatformFactory.php`). Wires `MockModelClient` + `MockResultConverter` into a real `Provider`/`Platform`.
- **Responses:** string (always `TextResult`), array model => result, or `\Closure(Model $model, array|string|object $input, array $options): ResultInterface|string` (`Test/ScriptedResponse.php`). Any `ResultInterface` passes through (`Test/MockResultConverter.php`), so `new ToolCallResult([new ToolCall('id1', 'ww_vote', ['target' => 3])])` works.
- **Closure input is the normalized payload**, not the `MessageBag`: `['messages' => [['role' => 'system', 'content' => …], …, ['role' => 'tool', 'content' => …, 'tool_call_id' => …]], 'model' => …]` (`Contract/Normalizer/MessageBagNormalizer.php:48-59`).
- **Options reach the closure:** `Agent::call($bag, $options)` → Runner → `platform->invoke(…, $options)` → closure 3rd arg; the Runner sets `options['tools']` to the toolbox tools (`Runner.php:259-285`). Custom keys (e.g. `seat`) survive.
- **Catalog:** `FallbackModelCatalog` accepts any name with all capabilities (`ModelCatalog/FallbackModelCatalog.php`); `qwen3:8b` is fine (`?` in a name would be parsed as options).
- **Results:** `Result\TextResult(string $content, …)`; `Result\ToolCallResult(ToolCall …)` throws on an empty list (`ToolCallResult.php:29-33`); `Result\ToolCall(string $id, string $name, array $arguments = [], ?string $signature = null)` (`ToolCall.php:24-29`).
- **Agent:** `new Agent(PlatformInterface, string $model, iterable $inputProcessors = [], iterable $outputProcessors = [], string $name = 'agent', ?ToolboxInterface $toolbox = null, ?ToolExecutorInterface $toolExecutor = null, ?int $maxToolCalls = 50, bool $excludeToolMessages = false, bool $includeSources = false, ?EventDispatcherInterface $ed = null)` (`Agent.php:44-56`). `call(string|MessageBag|UserMessage $input, array $options = []): Execution` (lazy; `getContent()` / `getResult()` drive it, `Execution.php:137, 160`).
- **Loop:** invoke → `ToolCallResult` → execute → add `Message::ofAssistant($result)` and one `Message::ofToolCall(…)` per call to the caller's bag (clone if `excludeToolMessages`) → invoke again; a `TextResult` ends it (`Runner.php:74-150`).
- No shipped `InMemoryPlatform`; ai-agent's `MockAgent` bypasses the Runner (unsuitable).

## Decision

Agent tests use `MockPlatformFactory::createPlatform($closure)` with the default catalog. A test helper (tests/, not lib/) `ScriptedSeatPlatform` holds `array<seat, list<ResultInterface|string>>`, finds the seat from a `seat` option (or the system message), pops the next item, and records payload + options (incl. `options['tools']` names for visibility asserts).

## Consequences

- No hand-written `PlatformInterface`; no BUGS.md entry.
- 029 confirmed: `Agent::call()` returns `Execution`; final text via `getContent()`.
