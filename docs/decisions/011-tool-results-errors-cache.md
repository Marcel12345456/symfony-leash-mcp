# 011 — Tool results, errors, addTool signature, cache hints

- Date: 2026-11-06
- Status: accepted
- PLAN reference: §3.2 `TransitionToolHandler`, `SchemaFactory`, Caching, place tool `readOnly`

## Question

How do closure tool handlers return results and errors, what is the exact `addTool` signature, and how are tools/list cache hints set (mcp/sdk 0.9.0)?

## Answer

Paths under `vendor/mcp/sdk/src`.

- A returned `CallToolResult` passes through unchanged (`CallToolHandler.php:107`); ctor `(array $content, bool $isError = false, mixed $structuredContent = null, ?array $meta = null)` (`Schema/Result/CallToolResult.php:49-54`). If the tool declares `outputSchema` and the result is not an error, structuredContent is validated; a mismatch is **replaced** by an isError result (`CallToolHandler.php:139,172-197`). Error results are not validated.
- Array returns: structuredContent auto-set for assoc arrays (`Capability/Registry/ToolReference.php:78-115`).
- Exceptions (`CallToolHandler.php:140-161`): `Mcp\Exception\ToolCallException` → `CallToolResult::error` with the message exposed; any other `\Throwable` → opaque JSON-RPC internal error. Input is validated against `inputSchema` **before** the handler (JSON-RPC invalid-params on failure).
- `InputRequiredResult` returns pass through (`:100-102`).
- `Builder::addTool(callable|array|string $handler, ?string $name, ?string $title, ?string $description, ?ToolAnnotations $annotations, ?array $inputSchema, ?array $icons, ?array $meta, ?array $outputSchema)` (`Server/Builder.php:701-711`); use named args. `new ToolAnnotations(readOnlyHint: true)` (`Schema/ToolAnnotations.php:39-45`).
- Cache: `Builder::setCachePolicy(Mcp\Server\Wire\CachePolicy)` (`Builder.php:362`); `CachePolicy::default(int $ttlMs, CacheScope $scope = Private)`, `->withMethod(...)`; enum `Mcp\Schema\Enum\CacheScope`. SDK default is already ttl 0 / private; applied on the 2026-07-28 wire only.

## Decision

- Tools return `CallToolResult` explicitly; success carries structuredContent `{state, availableTools, message}` (assoc array) matching an object `outputSchema` from `SchemaFactory`.
- Model-facing refusals: `CallToolResult::error([...])` or `ToolCallException`, never other exceptions. Validator messages are returned the same way.
- Because the SDK validates input against `inputSchema` first (JSON-RPC error, not actionable), the published inputSchema stays structural (types, required); Validator constraints that the SDK would reject first are still re-checked by the handler so messages stay actionable. The integration test pins which path a given invalid input takes.
- `setCachePolicy(CachePolicy::default(0, CacheScope::Private))` set explicitly.

## Consequences

- Unit test: structuredContent of a transition result validates against its declared outputSchema.
- No library bug found.
