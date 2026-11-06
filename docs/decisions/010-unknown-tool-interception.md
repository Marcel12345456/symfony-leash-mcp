# 010 — Unknown/invisible tool on tools/call: interception point

- Date: 2026-11-06
- Status: accepted
- PLAN reference: §3.2 WorkflowMcp, `UnavailableCapabilityResponder`

## Question

What does mcp/sdk 0.9.0 return when `tools/call` names a tool not registered on the per-request Builder, and how do we return our own actionable `CallToolResult(isError)`?

## Answer

Paths under `vendor/mcp/sdk/src`.

- Default: `Server/Handler/Request/CallToolHandler::handle()` → `Registry::getTool()` throws `ToolNotFoundException` (`Capability/Registry.php:281-285`, message `Tool not found: "<name>".`) → JSON-RPC error -32602 (`Error::forInvalidParams`). Not a `CallToolResult`, not actionable.
- Hook: `Builder::addRequestHandler(RequestHandlerInterface)` (`Server/Builder.php:479`). Interface: `supports(Request): bool`, `handle(Request, SessionInterface): Response|Error` (`Server/Handler/Request/RequestHandlerInterface.php:30,35`).
- Ordering: user handlers are merged **before** the defaults (`Builder.php:1122`); `Protocol` runs the first handler whose `supports()` is true (`Protocol.php:395-429`).
- `ListToolsHandler` has no filter (`ListToolsHandler.php:46-56`): registering invisible tools and hiding them is not possible without replacing list and call.
- `RequestEvent` (`Protocol.php:390`) can only replace the request, not answer it.

## Decision

Register only visible tools. `UnavailableCapabilityResponder` is a `RequestHandlerInterface` added per request; `supports()` is true only for a `CallToolRequest` whose name is **not** in this request's visible set, so visible calls still reach the default `CallToolHandler` (incl. MRTR) without delegation. `handle()` dispatches `CapabilityDenied` and returns `Response(CallToolResult::error([TextContent("… Available now: a, b")]))`.

## Consequences

- No inner-handler construction needed; no SDK workaround class.
- Test: invisible call → `isError: true` with visible tool names + `CapabilityDenied`; visible call unaffected.
- Observation (not a bug): SDK offers no tools/list filter or not-found hook.
