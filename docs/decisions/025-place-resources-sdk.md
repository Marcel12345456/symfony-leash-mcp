# 025 — Place resources: addResource handler, not-found routing, cache policy

- Date: 2026-11-10
- Status: accepted (the not-found code per protocol version is pinned by `WorkflowServerTest`)
- PLAN reference: §3.2 WorkflowMcp "Place resources" (M4); builds on decisions/004, 010, 011, 014

## Question

How do per-request `addResource()` handlers receive context and return content, how is an unregistered URI answered on `resources/read`, can we intercept it as 010 does for tools, and does the cache policy cover resources?

## Answer

mcp/sdk v0.9.0, paths under `vendor/mcp/sdk/src`.

- `Builder::addResource(\Closure|array|string $handler, string $uri, ?string $name, ?string $title, ?string $description, ?string $mimeType, ?int $size, ?Annotations, ?array $icons, ?array $meta)` (`Server/Builder.php:735-746`).
- Handler args are mapped by name: `uri`, `_session`, `_request` (`Server/Handler/Request/ReadResourceHandler.php:64-68`); a `RequestContext` parameter is injected (`Capability/Registry/InjectableParameters.php:29,53-54`).
- Returns (`Capability/Formatter/ResourceResultFormatter.php:51-112`): `ResourceContents` as-is; `string` → `TextResourceContents` with the registered mimeType; arrays → JSON (set mimeType `application/json`); `ReadResourceResult`/`InputRequiredResult` pass through (`ReadResourceHandler.php:85-89`).
- Unregistered URI: `ResourceNotFoundException` (`Capability/Registry.php:322-343`) → for revision ≥ 2026-07-28 `Error::forInvalidParams` (-32602, data `{uri}`), older revisions -32002 (`ReadResourceHandler.php:103-111`, `Schema/Enum/ProtocolVersion.php:140-143`). Other exceptions → -32603.
- Interception: `ReadResourceHandler::supports()` is `instanceof ReadResourceRequest` (`:45-48`); user handlers run before defaults (`Server/Builder.php:1122`), first match wins (decisions/010).
- `resources/list` returns everything registered (`Capability/Registry.php:298-320`): hiding = not registering.
- Cache: `Rev2026Codec::CACHEABLE_METHODS` includes `resources/list` and `resources/read` (`Server/Wire/Rev2026Codec.php:38-45`), so `CachePolicy::default(0, Private)` covers them.
- In-process `handleInline()` needs only the 014 `_meta` envelope.

## Decision

Register only the caller's visible resources per request (`addResource(handler, uri, name, description, mimeType)`). Add an unavailable-resource branch next to `UnavailableCapabilityResponder`: `supports()` only for a `ReadResourceRequest` outside the visible set; dispatches `CapabilityDenied`; returns `Error::forInvalidParams` with an actionable message and data `{uri, visible}` (resources/read has no `isError` result shape, so a JSON-RPC error is the only answer). Keep the existing cache policy.

## Consequences

- No SDK workaround class, no BUGS.md entry.
- Tests: hidden URI → -32602 with visible list + `CapabilityDenied`; visible URI read through the SDK handler; `resources/list` omits hidden URIs.
