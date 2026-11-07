# 014 — mcp/sdk 0.9.0 API cheat sheet for lib/WorkflowMcp

- Date: 2026-11-07
- Status: accepted (signatures read from source; the in-process snippet is pinned once the first lib test uses it)
- PLAN reference: §3.2 WorkflowMcp (UnavailableCapabilityResponder, TransitionToolHandler, endpoint); builds on 004, 010, 011

## Question

Which exact signatures, template annotations, argument mapping and in-process test route does lib/WorkflowMcp need?

## Answer

Paths under `vendor/mcp/sdk/src`.

- `Mcp\Schema\Request\CallToolRequest`: public readonly `string $name`, `array<string,mixed> $arguments` (absent → `[]`) (`Schema/Request/CallToolRequest.php:29-47`). `Request::getId(): string|int` (`Schema/JsonRpc/Request.php:79`).
- `Mcp\Schema\JsonRpc\Response(string|int $id, mixed $result)` (`Schema/JsonRpc/Response.php:37-41`); `Mcp\Schema\JsonRpc\Error::forInvalidParams(string, $id, $data)` (`Error.php:116`).
- `Mcp\Schema\Result\CallToolResult(array $content, bool $isError = false, mixed $structuredContent = null, ?array $meta = null)`; `::success(array, ?array)`, `::error(array, ?array)`: no structuredContent, use the ctor.
- `Mcp\Schema\Content\TextContent(mixed $text, ?Annotations)`: arrays/objects JSON-encoded.
- `Mcp\Server\Handler\Request\RequestHandlerInterface` is `@template-covariant TResult` (`:23`); implement with `@implements RequestHandlerInterface<CallToolResult>`. The server Builder does **not** share the client invariance bug in BUGS.md.
- `Builder`: `setServerInfo(name, version, ...)` (`:292`), `setCachePolicy` (`:362`), `addRequestHandler` (`:479`), `setSession(?store, ...)` (`:631`), `build()` (`:966`), `buildStateless(list<ProtocolVersion> = [V2026_07_28]): StatelessProtocol` (`:1004`). `Server::run(TransportInterface)`.
- `Mcp\Server\Wire\CachePolicy::default(int $ttlMs, CacheScope = Private)`; `Mcp\Schema\Enum\CacheScope`.
- `StreamableHttpTransport(ServerRequestInterface, ?ResponseFactory, ?StreamFactory, ?Logger, ?iterable $middleware, int $maxBodyBytes)`; serves stateless and legacy on one endpoint.
- Sessions: optional (default `InMemorySessionStore`). Stateless 2026-07-28 wire uses a throwaway session per request. Legacy `initialize` clients need a persistent store (`Psr16SessionStore`, 004).
- Closure args: `ReferenceHandler::prepareArguments` maps JSON args to params **by name**, casting per type; missing → default, then null. Extra args are ignored by the mapper. No raw-args parameter. Injectable: `Mcp\Server\RequestContext`, `Mcp\Server\ClientGateway`.
- In-process: `InMemoryTransport` discards replies (unusable). Use `$builder->buildStateless()->handleInline(string $json): StatelessResult` (`toJson()`, `isError()`). Each request needs `params._meta` with `io.modelcontextprotocol/protocolVersion: "2026-07-28"` and `io.modelcontextprotocol/clientCapabilities: {}`. No `initialize`.

## Decision

- Responder: `@implements RequestHandlerInterface<CallToolResult>`, returns `new Response($request->getId(), CallToolResult::error([new TextContent($msg)]))`, reads `$request->name`.
- Tool closures declare only a `RequestContext` parameter and read the raw `CallToolRequest` arguments from it, so one generic closure serves every DTO (accessor name still to confirm in `Server/RequestContext.php`). Fallback: a per-request `CallToolRequest` handler for visible names. `addTool` still carries inputSchema/outputSchema/annotations for listing.
- lib unit tests use `buildStateless()->handleInline()` with the `_meta` envelope via a test helper (tests/, not lib/). One HTTP-level integration test stays (header validation is skipped inline).

## Consequences

- No BUGS.md entry.
- Open: whether the SDK's inputSchema validation rejects unknown args (`additionalProperties: false` from SchemaFactory); pinned by a test.
