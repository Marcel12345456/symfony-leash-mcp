# 021 — Raw stateless client: headers, response format, SDK helpers, `_meta`

- Date: 2026-11-07
- Status: accepted
- PLAN reference: §3.3 HumanInput "Client side" (raw client, decisions/017); builds on 004, 014, 020

## Question

What must a hand-written PSR-18 client send to our `Server::builder()` + `StreamableHttpTransport` endpoint for a stateless 2026-07-28 `tools/call` (first call and MRTR retry), and can it reuse SDK helpers?

## Answer

mcp/sdk v0.9.0 (paths under `vendor/mcp/sdk/src/`).

- **Era routing** is by body: `params._meta["io.modelcontextprotocol/protocolVersion"]` marks a modern request (`Server/Wire/InboundClassifier.php:24-27, 161-176`). Without it the request takes the handshake path, which needs `Mcp-Session-Id`.
- **Required headers** (validator installed by default, `Builder.php:268, 1015`):
  - `MCP-Protocol-Version`, equal to `_meta`. Missing or mismatched → HTTP 400 (`Server/Stateless/StatelessProtocol.php:147-150, 305-331`).
  - `Mcp-Method`, equal to the body method. Otherwise 400 (`Server/Stateless/StandardHeaderValidator.php:54-67`).
  - `Mcp-Name` for `tools/call`, equal to `params.name`; the `=?base64?…?=` wrapping is decoded first. Otherwise 400 (`:76-104`, `Schema/Wire/McpHeader.php:42-52`).
  - `Mcp-Param-*` only for arguments annotated `x-mcp-header` (`:124-157`); we have none.
- **Not checked:** `Mcp-Session-Id` (each modern request gets a fresh in-memory session, `StatelessProtocol.php:194`), `Accept`, `Content-Type`. The SDK client sends `Content-Type: application/json` and `Accept: application/json, text/event-stream` (`Client/Transport/HttpTransport.php:204-205`). The default CORS/DNS-rebinding middleware still applies (`Server/Transport/StreamableHttpTransport.php:149-155`). Body limit 4 MiB; batches refused.
- **Response:** `application/json` (`Server/Transport/Http/StatelessResponder.php:61-66`). It is SSE (`data: <json>` frames) only if the handler emitted notifications and `Accept` contains `text/event-stream` (`StatelessProtocol.php:546-562, 948-958`). With `Accept: application/json` alone it stays JSON. `input_required` is a normal JSON result (`:575-577`); errors are JSON with 400/404.
- **Helpers:** all `final`, none `@internal` (only `Client/Transport/SseParser` is):
  - `Mcp\Client\Stateless\RequestEnvelope::__construct(ProtocolVersion, ClientCapabilities, Implementation $clientInfo, ?LoggingLevel = null)`; `stamp(array $payload): array` merges `_meta` (`Client/Stateless/RequestEnvelope.php:35-88`). Pure, standalone.
  - `Mcp\Client\Stateless\HeaderFactory::__construct(ToolCatalog $tools)`; `forMessage(array $payload, ProtocolVersion): array<string, string>` returns `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` (`HeaderFactory.php:32-78`). `new ToolCatalog()` is empty → no `Mcp-Param-*` (`ToolCatalog.php:41-44`).
  - `Mcp\Schema\Wire\McpHeader`: constants and `nameFor` / `encode` / `decode` (`Schema/Wire/McpHeader.php:28-80`).
  - `ClientCapabilities(elicitationForm: true)` → `{"elicitation":{"form":{}}}` (`Schema/ClientCapabilities.php:43, 176-181`); `Implementation(name, version)`.
- **`_meta`** (`Server/Stateless/RequestMeta.php:32-94`): `protocolVersion` and `clientCapabilities` required (else 400); `clientInfo`, `logLevel` optional. Without `elicitation.form` an `input_required` with an `ElicitRequest` is refused with 400 (`Server/Stateless/InputRequestCapabilities.php:73-77`).

Example first call: headers `MCP-Protocol-Version: 2026-07-28`, `Mcp-Method: tools/call`, `Mcp-Name: <tool>`; body `{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"<tool>","arguments":{…},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{"elicitation":{"form":{}}},"io.modelcontextprotocol/clientInfo":{"name":"leash","version":"dev"}}}}`. Retry: new `id`, same headers and `_meta`, plus `inputResponses` (object, entry shape per decisions/020) and the unchanged `requestState`.

## Decision

- The raw client builds `{jsonrpc, id, method, params:{name, arguments, [inputResponses, requestState]}}` by hand, stamps it with `RequestEnvelope` (`ClientCapabilities(elicitationForm: true)`), and takes headers from `HeaderFactory::forMessage(..., ProtocolVersion::V2026_07_28)`. It adds `Content-Type: application/json`, `Accept: application/json` (forces JSON) and `Authorization: Bearer <token>`. No `Mcp-Session-Id`.
- A `text/event-stream` response is parsed from its `data:` lines as a fallback (last JSON-RPC response wins).

## Consequences

- decisions/017 "built from the SDK's `RequestEnvelope` / `HeaderFactory` / `McpHeader`" confirmed; no hand-written headers.
- The round trip (first call → `inputRequests`/`requestState` → retry → complete result) is pinned by a test on the synthetic workflow and by `RawMcpClientEndpointTest` over HTTP.
- No SDK bug.
