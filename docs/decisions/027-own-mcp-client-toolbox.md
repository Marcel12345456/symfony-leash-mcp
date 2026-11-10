# 027 — Own MCP client toolbox instead of McpToolbox

- Date: 2026-11-10
- Status: accepted (revises the "reuse McpToolbox" part of 006)
- PLAN reference: §3.4 McpToolbox

## Question

Can `McpToolbox` + `ClientToolset` drive one seat turn over an in-process `Mcp\Client` with bare tool names (`ww_vote`) and forward unknown names to the server, or is a small own `ToolboxInterface` the better fit?

## Answer

symfony/ai-mcp-tool 0.15.0, symfony/ai-agent 0.15.0, mcp/sdk 0.9.0.

- **Construction:** `new ClientToolset(string $name, Client $client, TransportInterface $transport)` connects lazily itself (`ClientToolset.php:31-35, 124-137`); `Client::connect()` has no already-connected guard (`mcp/sdk/src/Client.php:93-100`), so a pre-connected client would initialize twice. `__destruct` disconnects (`ClientToolset.php:38-45`).
- **Prefix is mandatory:** `'' !== $prefix ? $prefix : $toolset->getName().'_'` (`McpToolbox.php:84`). The model sees `leash_ww_vote`, never `ww_vote`.
- **Results:** structuredContent → JSON via `ToolResultConverter`; else text blocks joined (`McpToolbox.php:133-185, 260-289`). `isError` → `ToolErrorException`; with `FaultTolerantToolbox` the model sees the server text (`FaultTolerantToolbox.php:40-41`). JSON-RPC errors (e.g. -32602) become `ToolExecutionException`, which fault-tolerant mode turns into a generic `An error occurred while executing tool "X".` (`ToolExecutionException.php:36-39`) — the actionable server message is lost.
- **Unknown names:** `AbstractToolbox::execute()` (final) throws `ToolNotFoundException` from `getMetadata()` before any MCP call (`AbstractToolbox.php:43, 109-118`). `McpToolbox` is `final`, its renderer private → forwarding needs a decorator that re-implements rendering.
- **Own toolbox contract:** `ToolboxInterface::getTools(): Tool[]`, `execute(ToolCall): ToolResult`; `new Tool(ExecutionReference, name, description, ?array $parameters, metadata)`, `new ExecutionReference(class, method)`, `new ToolResult(ToolCall, mixed $result)`. Empty `properties` must become a null schema (copy of `McpToolbox.php:89-92, 244-253`).

## Decision

Own generic `Leash\WorkflowMcp\Client\McpClientToolbox implements ToolboxInterface` (~80 lines, workaround class, no domain code) over a connected `Mcp\Client`: lists tools once per turn (follows `nextCursor`), bare names, `execute()` always calls `callTool()` so the server answers and logs `capability_denied`, renders structuredContent or text, maps `isError` and SDK exceptions to a model-visible `ToolResult` string. Resources are read with `Client::readResource()` and injected into the turn prompt (§3.4 "decide in M5").

## Consequences

- 006 "reuse McpToolbox + decorator" is superseded; one agent run per turn stays.
- Unit test: hidden name reaches the server, `isError` becomes text, empty-properties schema → null.
- BUGS.md: `McpToolbox` prefix cannot be disabled; JSON-RPC tool errors lose their message under `FaultTolerantToolbox`.
