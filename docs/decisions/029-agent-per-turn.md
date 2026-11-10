# 029 — One Agent per seat turn: construction, tool budget, transcript, think, timeout

- Date: 2026-11-10
- Status: accepted for construction, budget, transcript; proposed for the timeout (re-test BUGS.md Ollama http_client entry)
- PLAN reference: §3.4 Platform + Limits; follow-up to 006 and the `think` item in 002

## Question

How do we build a per-turn `Agent` in PHP (platform, model string, system prompt, own toolbox), cap it at 3 tool calls, capture a transcript, pass `think`, and set a per-request timeout?

## Answer

symfony/ai-agent, ai-ollama-platform, ai-bundle 0.15.0.

- **Construction:** `new Agent(PlatformInterface $platform, string $model, $inputProcessors = [], $outputProcessors = [], $name = 'agent', ?ToolboxInterface $toolbox = null, ?ToolExecutorInterface $toolExecutor = null, ?int $maxToolCalls = 50, …)` (`ai-agent/src/Agent.php:40-56`); no `AgentProcessor` in 0.15, the Runner exposes `$toolbox->getTools()` per run (`Runner.php:259-290`). System prompt: `SystemPromptInputProcessor` or `Message::forSystem()` in the bag. `FaultTolerantToolbox` catches only `ToolExecutionExceptionInterface` and `ToolNotFoundException` (`FaultTolerantToolbox.php:38-46`).
- **Platform:** bundle service `ai.platform.ollama`, autowirable as `PlatformInterface $ollama` (`AiBundle.php:241, 1156`); standalone `Bridge\Ollama\Factory::createPlatform(?endpoint, ?apiKey, ?HttpClientInterface, …)` (`Factory.php:66-74`).
- **Built-in cap counts rounds, not calls:** `++$iterations > $maxToolCalls` → `MaxIterationsExceededException`, the over-limit round is not executed, no final message (`Runner.php:121-122`).
- **Transcript:** `Agent::call(MessageBag, $options): Execution`; `getContent()` = final text. The passed bag is mutated: assistant tool-call message + one tool message per result (`Runner.php:129-131`); the final assistant text is not appended.
- **`think`:** `Agent::call($bag, ['think' => false])` reaches `/api/chat` top level (`OllamaClient.php:36, 88, 116-135`). Non-streaming results drop `message.thinking` (`OllamaResultConverter.php:62-84`).
- **Timeout:** the bridge wraps a passed client (`Factory.php:42-51`); no per-call timeout. The bundle passes its http_client reference (`AiBundle.php:1149`), which contradicts the BUGS.md entry — not re-run.

## Decision

- Built in PHP per turn: `new Agent($platform, $model, [], [], 'seat-N', new TurnBudgetToolbox($mcpClientToolbox, 3), maxToolCalls: 4)`, system prompt as `Message::forSystem()` in a `MessageBag` we keep; catch `MaxIterationsExceededException` (backstop for parallel calls). `TurnBudgetToolbox` answers calls beyond 3 with "tool budget for this turn is spent; stop".
- Transcript from the bag plus `getContent()`.
- `think` stays **on** by default (G0: thinking makes qwen3 play coherently), switchable with `LEASH_THINK=0`; per-turn latency measured in the live game.
- Per-turn timeout: own Ollama platform `Factory::createPlatform($endpoint, null, HttpClient::create(['timeout' => T, 'max_duration' => T]))` in one marked class; a timed-out turn ends without action and the moderator's deadline path applies.

## Consequences

- PLAN §3.4 "max tool calls" = `TurnBudgetToolbox` (counts calls); the built-in option counts rounds.
- Re-test the BUGS.md http_client entry with the self-built platform; correct or close it.
