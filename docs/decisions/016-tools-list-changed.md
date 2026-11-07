# 016 — tools/list_changed inside a tools/call stream: not pursued

- Date: 2026-11-07
- Status: accepted
- PLAN reference: M2 "Tool-list change notification [U]" VERIFY

## Question

Can mcp/sdk 0.9.0 emit `notifications/tools/list_changed` in the SSE response of a `tools/call` on our per-request server (2026-07-28 and legacy wire), and does MCP Inspector act on it?

## Answer

mcp/sdk v0.9.0, MCP Inspector 2.14.0.

- **Mechanically possible on the 2026 wire.** A handler can call `$context->getClientGateway()->notify(new ToolListChangedNotification())` (`vendor/mcp/sdk/src/Server/RequestContext.php:94`, `ClientGateway.php:100-102`). `StatelessProtocol::readNotification` (`StatelessProtocol.php:721-746`) forwards it as an SSE frame before the result (`:546`), but only when the client sent `Accept: text/event-stream` (`:952`); otherwise it is dropped (`:553-558`). Legacy wire: `Protocol.php:439-465` queues it; not run.
- **The SDK's own list_changed route is different.** `Builder::setNotificationBus` (`Builder.php:324`) + `RegistryChangePublisher` publish registry mutations to a bus that only open `subscriptions/listen` streams read (`StatelessProtocol.php:347-384`), filtered by `NotificationFilter::carries`. `listChanged` is advertised only with a bus and a publishing registry (`Builder.php:1162`) and is deliberately stripped from legacy `initialize` (`InitializeHandler.php:56`, CHANGELOG.md:43). Our registry is rebuilt per request, so no mutation event ever fires.
- **Spec 2026-07-28 forbids the manual emit.** `server/tools.md`: list_changed goes to clients with an open `subscriptions/listen` stream that asked for `toolsListChanged`. `transports/streamable-http.md`: notifications on a request's SSE stream "MUST relate to the originating client request". `patterns/subscriptions.md`: the server "MUST NOT send notification types the client has not explicitly requested".
- **Inspector 2.14.0** registers the handler only if `capabilities.tools.listChanged` is true (`core/mcp/inspectorClient.ts:2145-2160`) and then shows a manual "List updated / Refresh" indicator. On the legacy wire the SDK never advertises it, so the notification would be ignored.

## Decision

Do not build it. Tool lists stay per-request; agents re-list every step (decisions/005). The `tools/call` result already carries `availableTools` (decisions/011), which is the demo's "tools changed" signal. A conformant route (shared `Psr16NotificationBus` + `setNotificationBus` + client `subscriptions/listen`) is out of scope for the hackathon.

## Consequences

- PLAN M2: list_changed task closed as "not pursued", citing this record.
- No BUGS.md entry (`InitializeHandler.php:56` is intentional).
- In the Inspector, the human re-lists after a transition.
