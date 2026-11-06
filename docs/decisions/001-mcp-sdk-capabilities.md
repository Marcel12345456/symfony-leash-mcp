# 001 — mcp/sdk capabilities: MRTR, elicitation, cache hints (protocol path)

- Date: 2026-11-06
- Status: proposed — protocol path is gate G0, a human decision
- PLAN reference: §3.3 "Expected wire shapes" VERIFY; §3.2 Caching VERIFY; Phase 0 output "Path A/B/C"

## Question

Does mcp/sdk 0.9.0 implement MRTR (`input_required`, `requestState`, `inputResponses`), 2026-07-28 negotiation and list cache hints, and do the plan's wire shapes match the spec?

## Answer

mcp/sdk v0.9.0, symfony/mcp-bundle v0.15.0.

**SDK**
- `ProtocolVersion::V2026_07_28` (`vendor/mcp/sdk/src/Schema/Enum/ProtocolVersion.php:46`). `StreamableHttpTransport` serves both eras on one URL; modern-only via `Builder::withoutModernEra()`/`setModernVersions()` (`Builder.php` ~931-946).
- `Mcp\Schema\Result\InputRequiredResult(array $inputRequests, ?string $requestState)` → `{resultType:"input_required", inputRequests:{key:{method,params}}, requestState}` (`Schema/Result/InputRequiredResult.php:30-79`). A tool handler may return it (`Server/Handler/Request/CallToolHandler.php:99-102`).
- Retry: `StatelessProtocol::liftInputContext` reads `params.inputResponses` + `params.requestState` and verifies the state (`Server/Stateless/StatelessProtocol.php:900-927`); handler reads `RequestContext::getInputContext()` → `elicitResult($key)`, `requestState()` (`RequestContext.php:108`, `InputContext.php:61,121`).
- `RequestContext::mintRequestState(array $payload)` → HMAC-signed `base64(json{exp,data}).mac` (`RequestContext.php:151`, `Server/Stateless/RequestStateCodec.php:51`); key ≥ 32 bytes via `Builder::setRequestState($key, $ttl)` (`Builder.php:380`) or bundle `request_state.{key,ttl}` (`mcp-bundle/config/options.php:159-166`). Bad state → 400 `Invalid params` (`StatelessProtocol.php:509-511`). The codec does **not** bind caller/tool/args or enforce single use.
- Cache hints: `Builder::setCachePolicy(CachePolicy::default(ttl)->withMethod('tools/list', 0, CacheScope::Private))` (`Builder.php:350-368`, `Server/Wire/CachePolicy.php`); `Rev2026Codec::fillCacheHints` writes `ttlMs` + `cacheScope` (`Rev2026Codec.php:89-119`); bundle `cache.{ttl_ms,scope,methods}`.
- `ClientGateway::elicit()` is synchronous; on 2026-07-28 it becomes `input_required` and the handler is re-entered on retry (`Server/Stateless/ElicitationReplay.php`, `ClientGateway.php:205-235`). Handshake era: needs session + open stream; `InputRequiredShim` converts a returned `InputRequiredResult` into `elicitation/create` (`Server/InputRequiredShim.php`).
- Client defaults to 2026-07-28 (`server/discover`, falls back to `initialize`); `Client\Protocol` auto-resolves `inputRequests` through elicitation handlers, up to 10 rounds, re-sending `inputResponses` + `requestState` (`Client/Protocol.php:68, 572-619`; `Client/Stateless/InputRequestResolver.php`). Synchronous resolver: a deferred human answer needs either a "pending" handler or driving the raw call ourselves.

**Spec** (`https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/mrtr`, `…/client/elicitation`, `…/server/utilities/caching`)
- Result `{"resultType":"input_required","inputRequests":{<key>:{"method":"elicitation/create","params":{"mode":"form","message":…,"requestedSchema":{…}}}},"requestState":"<opaque>"}`; valid on `tools/call`, `prompts/get`, `resources/read`.
- Retry: new JSON-RPC id, same method/params + `params.inputResponses.<key> = {action, content?}` + `params.requestState` echoed exactly.
- Every request carries `_meta` (`io.modelcontextprotocol/protocolVersion`, `/clientInfo`, `/clientCapabilities`); `elicitation:{form:{}}` must be declared.
- Cache hints `ttlMs` (int ms) and `cacheScope` (`public|private`) top-level on list/read/discover results; never on `input_required`; retries with `inputResponses`/`requestState` must not be cached.

**Differences vs PLAN:** field names match. (1) retry example lacks `_meta` (SDK client adds it); (2) "non-shared scope" = `cacheScope:"private"`, `ttlMs:0`; (3) requestState not caller-bound by the SDK → bound fields go into our payload; (4) SDK TTL is global (default 600 s) → set 86400; (5) no single-use guarantee → our `ProcessedInputRequest`.

## Decision (proposed for G0)

**Path: A** — use the SDK's MRTR on Streamable HTTP.
- Tools return `InputRequiredResult` (built from `ElicitRequest::forForm`) instead of blocking `ClientGateway::elicit()`, because the deferred flow must end the request.
- `lib/HumanInput` keeps only what the SDK lacks: payload binding (`subjectRef, capability, argsHash, callerId, nonce, expiresAt`) verified after `getInputContext()->requestState()`, idempotency store, `PendingInput`, notifier.
- Signing key from `kernel.secret` (≥ 32 bytes), `request_state.ttl` 86400; per-state expiry via our own `expiresAt`.
- Client side (agent): avoid the auto-resolving loop for deferred answers — "pending" elicitation handler or raw first call + later re-send. Spike in Proof 1 / M2.

## Consequences

- PLAN §3.3 wire shapes confirmed; add `_meta` note. `InputRequiredResult` value object becomes a thin wrapper of the SDK class. Path B/C not needed.
- PLAN §3.2 Caching: `setCachePolicy` with `ttl_ms: 0`, `scope: private`.
- BUGS.md candidate: codec TTL is global, no per-state expiry.
- Proof 1 must show an `input_required` round-trip over the in-process HTTP transport.
