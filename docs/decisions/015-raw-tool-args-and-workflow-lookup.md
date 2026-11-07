# 015 — Raw tool args in a RequestContext closure; workflow lookup by name

- Date: 2026-11-07
- Status: accepted (locator keying is pinned by the endpoint test)
- PLAN reference: §3.2 WorkflowMcp (`/mcp/{workflow}/{subjectId}`, TransitionToolHandler)

## Question

How does an `addTool` closure get the raw arguments and tool name, and how do we fetch a workflow by name without a subject? (mcp/sdk 0.9.0, framework-bundle 8.1.11, workflow 8.1.8)

## Answer

- `Mcp\Server\RequestContext::getRequest(): Mcp\Schema\JsonRpc\Request` (`Server/RequestContext.php:52`); it is the real `CallToolRequest` (`CallToolHandler.php:90`), so narrow with `instanceof`. Read `->name`, `->arguments`.
- A closure with only a `RequestContext` param works next to an inputSchema with properties: `ReferenceHandler::prepareArguments` loops over the closure's params only (`:108`); `RequestContext` is injected (`InjectableParameters`).
- Before the handler, arguments are validated against the tool's `inputSchema` with Opis; any failure (wrong type, missing required, `additionalProperties: false`, and constraint keywords such as `maximum`/`minLength`) → JSON-RPC `-32602` with `data.validation_errors`, not a `CallToolResult` (`CallToolHandler.php:70-77`). Unknown tool → `-32602` (`:62`).
- Workflow services: ids `workflow.<name>` / `state_machine.<name>` (`FrameworkExtension.php:1036`), tag `workflow` with attribute `name` (`:1149-1154`). `Registry::get()` needs a subject.

## Decision

- Tool closures take `RequestContext $context`, narrow to `CallToolRequest`, pass `name` + `arguments` to our handler.
- The published inputSchema is **structural only**: types, enums, items, descriptions, required, `additionalProperties: false`. Validator constraint keywords are **not** published, so constraint violations reach the Validator and come back as actionable `CallToolResult` errors (done-signal). This refines decision 013 and follows decision 011.
- Endpoint looks up the workflow with `#[AutowireLocator('workflow', indexAttribute: 'name')] ContainerInterface`; unknown name → 404.

## Consequences

- Shape errors (wrong type, missing field, unknown field) stay JSON-RPC -32602 from the SDK; the integration test pins one of each path.
- No library bug.
