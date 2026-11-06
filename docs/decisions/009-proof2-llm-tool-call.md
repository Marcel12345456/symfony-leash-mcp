# 009 — Proof 2: chat completion with one tool call through Symfony AI (Ollama)

- Date: 2026-11-06
- Status: accepted (demonstrated)
- PLAN reference: Phase 0 task 5; deviation: Ollama instead of HF (no HF credits, PROGRESS Decisions)

## Question

Does a Symfony AI agent call a tool and use its result, with token usage, on the provider we can afford?

## Answer

Yes. `bin/console spike:llm-tool` (Ollama on the host via `OLLAMA_ENDPOINT=http://host.docker.internal:11434`, `qwen2.5:7b`, ~17 s on Apple Silicon):

```
tool calls: [C1]
answer: The order amount of case C1 is 42.50 euros (…4250 cents…).
token usage: 2 model calls, prompt 425, completion 64, total 489
```

Wiring: `config/packages/ai.yaml` (`platform.ollama.endpoint`, `agent.spike` with `tools.services`), tool `#[AsTool]` on `App\Spike\CaseAmountTool` (int cents), agent injected as `ai.agent.spike`. Token usage is a `Symfony\AI\Platform\TokenUsage\TokenUsageAggregation` in `$result->getMetadata()->get('token_usage')`, aggregated over all model calls of the run (`getTokenUsages()`; it has `count()` but is not `Countable`).

## Decision

Ollama is the dev/eval provider; model from env. SpendGuard counts prompt + completion itself (Ollama gives no total). Proof 2 is not part of `make qa` (no real LLMs in tests).

## Consequences

- BUGS.md: Ollama bridge reports no total tokens.
- HF path stays possible via the same `ai.yaml` if credits appear.
