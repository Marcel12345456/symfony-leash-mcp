# 006 — Symfony AI toolbox contract and ai-mcp-tool

- Date: 2026-11-06
- Status: proposed (design consequence for the agent loop needs the human's OK)
- PLAN reference: §3.4 McpToolbox, both VERIFY items

## Question

What is the toolbox contract, is the tool list re-read per model call, what happens to unknown tool names, and does `symfony/ai-mcp-tool` already provide an MCP-client toolbox?

## Answer

symfony/ai-* 0.15.0, mcp/sdk 0.9.0.

- **Contract:** `ToolboxInterface::getTools(): Tool[]`, `execute(ToolCall): ToolResult` (`vendor/symfony/ai-agent/src/Toolbox/ToolboxInterface.php:22-34`). `Tool` = reference, name, description, `?array $parameters` (JSON Schema), metadata. `AbstractToolbox::execute()` is final: dispatches `ToolCallRequested` → `invoke()` → `ToolCallSucceeded|Failed` (`AbstractToolbox.php:41-87`).
- **Tool list per run is a snapshot:** `Runner::run()` calls `exposeTools()` once before the loop (`Execution/Runner.php:76`, `:278-287`); every model call reuses the same options (`:84`). A comment at `:304` says tools can change during a run, but the model-facing list is never refreshed.
- **Unknown names:** `Runner` does not drop them (`:293, 307-308`); `AbstractToolbox::execute()` throws `ToolNotFoundException` in `getMetadata()` *before* `ToolCallRequested` (`AbstractToolbox.php:43, 117`). Without `fault_tolerant_toolbox` the run aborts; with it the model gets `Tool "X" was not found, please use one of these: …` (`FaultTolerantToolbox.php:42-48`).
- **ai-mcp-tool ships an MCP-client toolbox:** `Symfony\AI\Agent\Bridge\Mcp\McpToolbox extends AbstractToolbox` + `ClientToolset` (wraps `Mcp\Client` + transport, lazy connect). Caches tools until `reset()` (`McpToolbox.php:33, 62-65, 110-113`), names prefixed `<server>_` (`:84`), unknown names rejected as above, remote `isError` → `ToolErrorException` (`:141-149`). No resources, prompts or elicitation (`ai-mcp-tool/README.md:6-9`). Auth via mcp-bundle client transport `headers` (`mcp-bundle/config/options.php:314`).
- **Custom toolbox with ai-bundle:** no config key; decorate `ai.toolbox.<name>` or define the `Agent` service yourself (`Agent.php:50-63`).

## Decision (proposed)

- Reuse `McpToolbox` + `ClientToolset`; no MCP-client toolbox from scratch.
- One generic decorator in `lib/WorkflowMcp` (workaround, clearly marked): `reset()` + re-list per step, and forward unknown tool names to `toolset->callTool()` so the server's `UnavailableCapabilityResponder` records `capability_denied`.
- **One agent run per workflow step** (or a thin custom loop over `Platform::invoke`), because the Runner freezes the tool list for a whole run. Fits G3: the workflow, not the LLM, drives the loop.
- Resources: expose via a small read tool or context injection (Phase 1 decision, as planned).
- Elicitation: `ElicitationCallbackInterface` on the SDK client (see 005).

## Consequences

- PLAN §3.4: "VERIFY whether Symfony AI ships an MCP-client toolbox" → use ai-mcp-tool `McpToolbox`; "Re-lists after every call" → decorator resets per step, one run per step; drop the "hook in there" branch.
- Enable `fault_tolerant_toolbox`.
- BUGS.md candidate: Runner never refreshes `$options['tools']` despite the comment at `Runner.php:304` (repro: toolbox whose `getTools()` changes after the first `execute`).
