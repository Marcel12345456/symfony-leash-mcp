# 005 — mcp/sdk client API, tool schemas, list_changed

- Date: 2026-11-06
- Status: accepted
- PLAN reference: §3 SchemaFactory VERIFY; §3 Caching (list_changed); §3.4 McpToolbox client side

## Question

How does an mcp/sdk 0.9.0 client talk Streamable HTTP with a bearer token and handle elicitation, which tool schemas does the SDK derive or accept, and is list_changed emitted and surfaced?

## Answer

- **Client:** `Mcp\Client::builder()` (`vendor/mcp/sdk/src/Client.php:81`) → `setClientInfo`, `setCapabilities(ClientCapabilities)`, `addRequestHandler`, `addNotificationHandler`, `build` (`Client/Builder.php:59-190`). Transport `Client\Transport\HttpTransport(string $endpoint, array $headers = [], ?ClientInterface, ?RequestFactoryInterface, ?StreamFactoryInterface, …, bool $listen = false, …)` (`HttpTransport.php:117-128`); custom headers applied on every POST and win on conflict (`:200-215`). PSR-18/17 auto-discovered (`:134-136`).
- **Gotcha:** no PSR-7 implementation installed; Symfony `Psr18Client` throws without `nyholm/psr7` (`vendor/symfony/http-client/Psr18Client.php:66-69`).
- **Synchronous API:** `connect($transport)` (`Client.php:93`), `listTools`, `callTool(name, args, …)`, `listResources`, `readResource(uri)`, `listResourceTemplates`, `disconnect` (`:186-435`); each request runs in a `\Fiber` (`:408-428`).
- **Elicitation (client):** `addRequestHandler(new ElicitationRequestHandler(ElicitationCallbackInterface))`, callback `__invoke(ElicitRequest): ElicitResult`; declare `new ClientCapabilities(elicitation: true)`. Arrives on the SSE stream of the in-flight POST, answered inside the blocking `callTool()`.
- **Schemas:** `Server\Builder::addTool(…, ?array $inputSchema, …, ?array $outputSchema)` (`Builder.php:703-713`); loader uses `inputSchema ?? SchemaGenerator::generate()` (`ReflectedElementLoader.php:115`), outputSchema only if explicit (`:125`). `SchemaGenerator` derives input from `#[Schema]` → reflection types → docblocks; never output. `CallToolResult::structuredContent` supported (`Schema/Result/CallToolResult.php:52,146`); must be a JSON object up to 2025-11-25.
- **list_changed:** server publishes via `RegistryChangePublisher` only with `Builder::setNotificationBus()` (`Builder.php:324,1161`); `InitializeHandler.php:56` calls `withoutListChanged()` (not investigated). Client has no list_changed handler (only logging/progress); GET listen stream needs a streaming PSR-18 client.

## Decision

- Client: `Mcp\Client` + `HttpTransport` with `Authorization: Bearer`, Symfony `Psr18Client` + `nyholm/psr7`.
- Agents re-list tools every step (connect → listTools → callTool); list_changed is a server-side nicety only.
- `SchemaFactory` builds explicit `inputSchema` and `outputSchema` arrays from DTOs + Validator constraints and passes them to `addTool`. `structuredContent` stays a JSON object.

## Consequences

- composer: add `nyholm/psr7`.
- PLAN §3 SchemaFactory: pass explicit arrays; SDK infers input only.
- PLAN §3 Caching: client does not consume list_changed; agents re-list per step.
- Elicitation HTTP round-trip still to be proven in Proof 1.
