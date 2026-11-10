# 031 — API cheat sheet for McpClientToolbox and TurnBudgetToolbox

- Date: 2026-11-10
- Status: accepted for signatures; proposed for the in-process session reuse (pinned by the toolbox unit test)
- PLAN reference: §3.4 McpToolbox; follow-up to 027 and 014

## Question

Which exact vendor APIs does `McpClientToolbox implements ToolboxInterface` use over a connected `Mcp\Client`, plus `TurnBudgetToolbox`, and how does a lib test run a `Builder` in-process behind `HttpTransport`?

## Answer

mcp/sdk 0.9.0, symfony/ai-agent 0.15.0, symfony/ai-platform 0.15.0.

**ai-agent / ai-platform**
- `Symfony\AI\Agent\Toolbox\ToolboxInterface`: `getTools(): Tool[]`, `execute(ToolCall $toolCall): ToolResult` (`@throws ToolExecutionExceptionInterface`, `ToolNotFoundException`, both in `Toolbox\Exception\`).
- `Symfony\AI\Agent\Toolbox\ToolResult(ToolCall $toolCall, mixed $result, ?SourceCollection $sources = null)`; `getToolCall()`, `getResult()`.
- `Symfony\AI\Platform\Tool\Tool(ExecutionReference $reference, string $name, string $description, ?array $parameters = null, array $metadata = [])` (`Tool/Tool.php:27`); `Tool\ExecutionReference(string $class, string $method = '__invoke')`.
- `Symfony\AI\Platform\Result\ToolCall(string $id, string $name, array $arguments = [], ?string $signature = null)`; `getId()`, `getName()`, `getArguments()`.

**mcp/sdk client** (`src/Client.php`)
- `listTools(?string $cursor = null): ListToolsResult` (`:196`): `tools` (`Mcp\Schema\Tool[]`), `?string $nextCursor`. `Mcp\Schema\Tool`: `name`, `?description`, `array $inputSchema`, `?outputSchema`, `?annotations`.
- `callTool(string $name, array $arguments = [], ?callable $onProgress = null, ?CancellationTokenInterface $cancellation = null, ?float $timeoutSeconds = null): CallToolResult` (`:232`).
- Errors (`Mcp\Exception\`): JSON-RPC error → `RequestException` (`getMessage()` = server message, `getCode()` = JSON-RPC code, `getError(): ?JsonRpc\Error`; `Client.php:424`); `TimeoutException extends RequestException`; `ConnectionException` (transport, not connected); `RequestCancelledException` (not a `RequestException`); `InvalidArgumentException`, `RuntimeException`.
- `CallToolResult`: `content` (`Content[]`), `isError`, `structuredContent`, `meta`. `TextContent::$text` (mixed).
- `readResource(string $uri, ?callable $onProgress = null): ReadResourceResult` (`:291`): `contents` (`TextResourceContents` with `text` | `BlobResourceContents` with base64 `blob`), `ttlMs`, `cacheScope`.

**In-process server**
- `Mcp\Server\Session\InMemorySessionStore(int $ttl = 3600, ClockInterface $clock = new NativeClock())`; state lives in the instance, so one instance reused across `build()` calls keeps the session. Without `setSession()`, every `build()` gets a fresh store (`Builder.php:1053`).
- `Server::run(TransportInterface): mixed` returns `StreamableHttpTransport::listen(): ResponseInterface`.
- `StreamableHttpTransport` default middleware includes CORS and DNS-rebinding protection: send `Host: localhost`.

## Decision

- `McpClientToolbox::getTools()` follows `nextCursor` once per turn and maps each tool to `new Tool(new ExecutionReference(self::class, 'execute'), $t->name, $t->description ?? '', $schema)` (null schema for empty properties, 027).
- `execute()` keeps 027: never throws for server answers. `isError` → text; `RequestException`/`ConnectionException`/`RequestCancelledException` → `ToolResult` text with the server message and code. Success → structuredContent as JSON, otherwise joined text.
- `TurnBudgetToolbox` counts `execute()` calls and returns a text "budget spent; stop" result past the cap (029).
- Lib test: a PSR-18 fixture that runs `$builder->setSession($sharedStore)->build()->run(new StreamableHttpTransport($request, $f, $f))` per request, with `Host: localhost`.

## Consequences

- No BUGS.md entry. If the session does not survive between `build()` calls, the fixture records it here.
