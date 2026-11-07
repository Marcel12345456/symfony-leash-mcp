# 017 — Client side MRTR: raw first call and raw resume

- Date: 2026-11-07
- Status: accepted
- PLAN reference: §3.3 HumanInput "Client side"; decisions/001 open item "avoid the auto-resolving loop for deferred answers"

## Question

With mcp/sdk 0.9.0 and symfony/ai-mcp-tool 0.15.0 on 2026-07-28, how does a client get the raw `input_required` result, resume it later from another process, and what does the server handler see on accept, decline, cancel and a bad `requestState`?

## Answer

mcp/sdk v0.9.0, symfony/ai-mcp-tool v0.15.0.

- **No hook for the raw result.** `Client::callTool()` (`vendor/mcp/sdk/src/Client.php:232-258`) calls `Protocol::request()` (`Client/Protocol.php:555-620`), which loops up to `MAX_ROUND_TRIPS=10` (`:68`) and resolves `input_required` through `InputRequestResolver` (`Client/Stateless/InputRequestResolver.php:73-80`), re-sending `inputResponses` + `requestState` (`Protocol.php:600-611`). `ElicitationCallbackInterface::__invoke(ElicitRequest): ElicitResult` never sees `requestState`. A throwing callback becomes an `Error` and then a message-only `RuntimeException` (`ElicitationRequestHandler.php:50-62`, `InputRequestResolver.php:~120-185`). Answering `Cancel` is a real answer, not a defer.
- `McpToolbox::invoke()` → `ClientToolset::callTool()` → `Client::callTool` (`McpToolbox.php:137`, `ClientToolset.php:78-88`): same blind spot.
- **No public retry API.** `callTool` has no place for `inputResponses`/`requestState`; the server reads them from `params`, not `params.arguments` (`Server/Stateless/StatelessProtocol.php:~895-903`). A raw POST works from any process: the state codec is keyed only by the signing key (`Server/Stateless/RequestStateCodec.php:40-92`). Shape: `{jsonrpc,id,method:"tools/call",params:{name,arguments,inputResponses:{<key>:{action,content?}},requestState,_meta:{…}}}` (`Client/Stateless/RequestEnvelope.php:50-75`), headers from `HeaderFactory.php:38-70` (`McpHeader` constants) plus `Authorization: Bearer`. `inputResponses` must encode as a JSON object (`Protocol.php:596-601`).
- **Server side.** `RequestContext::getInputContext(): ?InputContext` (`Server/RequestContext.php:108`) is null on a first call. `InputContext::elicitResult($key)` returns an `ElicitResult` with `isAccepted()` / `isDeclined()` / `isCancelled()` (`Schema/Result/ElicitResult.php:44-70`); accept without content, malformed answer or missing key → null ("re-ask"). `has()`, `response()`, `all()`, `requestState()` give raw access.
- **Bad state is rejected by the SDK before the handler runs:** `RequestStateCodec::verify` throws on malformed / mac / expired (`:53-92`); `StatelessProtocol.php:505-512` answers `Error::forInvalidParams('The supplied requestState failed verification.')`, HTTP 400. Same if no codec is configured (`:920-922`).
- The codec is not caller-bound; default TTL 600 s (`Builder::setRequestState($key, $ttl)`, `Builder.php:380`).

## Decision

- `lib/HumanInput` gets one small raw client: JSON-RPC `tools/call` over PSR-18 with a Bearer token, built from the SDK's `RequestEnvelope` / `HeaderFactory` / `McpHeader` helpers. It returns either an `InputRequiredResult` or the final `CallToolResult`, and sends the resume with stored arguments + `inputResponses` + `requestState` and the original caller's token. Marked as the workaround for the BUGS.md limitation.
- Agents: a decorator in front of the toolset calls the raw client; on `input_required` it hands off to `InputRequestHandlerInterface` (persist `PendingInput`, notify) and returns the "paused, do not retry" text. `McpToolbox` stays unchanged.
- `Mcp\Client` stays for non-deferred paths.
- Server: `setRequestState(kernel.secret-derived key, ttl ≥ 24 h)`; our own payload (`subjectRef, capability, argsHash, callerId, nonce, expiresAt`) is checked in the handler. SDK-level rejects (tampered/expired SDK envelope) surface as JSON-RPC -32602; our checks (wrong caller, args mismatch, own expiry) return actionable `CallToolResult` errors.

## Consequences

- PLAN §3.3 client side: "raw first call + raw resume" (no pending elicitation handler); decisions/001 open item closed.
- BUGS.md: `Client::callTool` cannot surface `input_required` or send `inputResponses`.
- Tests use the in-process transport (decisions/014) and the synthetic workflow.
- Build-time check: how `ToolsetInterface` is injected into `McpToolbox` (decorator point).
