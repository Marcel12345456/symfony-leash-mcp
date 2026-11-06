# 004 — Per-request MCP server in a Symfony controller

- Date: 2026-11-06
- Status: accepted (two open points checked by Phase 0 Proof 1)
- PLAN reference: §3 "Building the server per request" VERIFY; place resources / resource templates VERIFY

## Question

Can an `mcp/sdk` `Server` be built per HTTP request in a controller at `/mcp/{workflow}/{subjectId}`, with closure-registered tools and resources, and do sessions survive the rebuild?

## Answer

mcp/sdk 0.9.0, symfony/mcp-bundle 0.15.0.

- **Per-request build: yes.** `Server::builder()` returns a fresh `Builder` (`vendor/mcp/sdk/src/Server.php:42`). `addTool(callable|array|string $handler, ?name, ?title, ?description, …, ?inputSchema, …)` (`Builder.php:701`), `addResource(…, string $uri, …)` (`:735`), `addResourceTemplate(…, string $uriTemplate, …)` (`:770`); discovery is optional (`setDiscovery`, `:668`); `build()` (`:966`) registers eagerly (`:1068-1075`). Registry also mutable at runtime (`Capability/Registry.php:118/134/144`).
- **Transport:** `Mcp\Server\Transport\StreamableHttpTransport(ServerRequestInterface $request, ?ResponseFactoryInterface, ?StreamFactoryInterface, ?LoggerInterface, ?iterable $middleware, int $maxBodyBytes)` (`StreamableHttpTransport.php:95-102`); `$server->run($transport)` returns a PSR-7 response (`Server.php:54-78`). Reference: `vendor/symfony/mcp-bundle/src/Controller/McpController.php:38-52` (HttpFoundation↔PSR-7 bridge, `MiddlewareFactory::create()`, streamed response when `text/event-stream`). `middleware: []` logs a warning per request (`StreamableHttpTransport.php:119-123`). A `StatelessHttpTransport` also exists (not examined).
- **Sessions:** header `Mcp-Session-Id` (UUID, `StreamableHttpTransport.php:59`, `:499-519`). Created on `initialize` via `SessionManager::create()` (`Protocol.php:1118-1143`); non-initialize without id → 400 (`:1145-1150`). State lives only in `SessionStoreInterface`; default `InMemorySessionStore` (`Builder.php:1052-1053`) is lost per request. Persistent options: `FileSessionStore`, `Psr16SessionStore(CacheInterface, $prefix, $ttl)`, bundle `FrameworkSessionStore`; set via `Builder::setSession()` (`:631`); optional `setSessionLock()` (`:656`). The session does not store the registry, so each request may register a different tool set.
- **mcp-bundle:** static routes per configured server (`Routing/RouteLoader.php:43-49`), servers built at compile time, registries filled by compiler passes (`McpBundle.php:295-323`). No route variables. Reusable parts: controller pattern, `MiddlewareFactory`, PSR-7 bridge services, `FrameworkSessionStore`.

## Decision

Own controller for `/mcp/{workflow}/{subjectId}` modelled on `McpController`. Build `Server::builder()` per request; register tools/resources/templates as closures from workflow state ∩ actor role. `StreamableHttpTransport` + bundle PSR-7 bridge + `MiddlewareFactory`. Persistent session store (`Psr16SessionStore` on a Symfony cache pool), prefix per workflow+subject; `SymfonyLockSessionLock` if concurrent requests per session matter. Authorization in the controller (session id is not bound to a role). Do not route these endpoints through `mcp.servers`.

## Consequences

- `lib/WorkflowMcp`: endpoint controller + server factory (workflow, subject, actor → `Server`).
- Open, checked in Proof 1: status for an unknown session id after rebuild (`Protocol.php` > line 1161); a tool set that changes between requests of one session.
- `list_changed` needs `setNotificationBus` (`Builder.php:324`); clients re-list anyway (PLAN §3 Caching).
- No bug found.
