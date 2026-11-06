# 008 — Proof 1: controller-hosted MCP server, PHP client, input_required round-trip

- Date: 2026-11-06
- Status: accepted (demonstrated)
- PLAN reference: Phase 0 task 4; open points of decisions 001, 004, 005

## Question

Does the recipe from 001/004/005 work end to end: per-request server in a Symfony controller, dynamic tool + resource, mcp/sdk client over Streamable HTTP, `input_required` round-trip?

## Answer

Yes. `bin/console spike:mcp` (starts `php -S`, then uses `Mcp\Client` + `HttpTransport` + Symfony `Psr18Client` + nyholm) prints:

```
tools(closed): []
tools(open): [spike_ask]
resource: subject open is open
elicitation asked: Approve: refund 1200 cents?
tool result: approved: refund 1200 cents
```

Code: `src/Spike/SpikeMcpController.php`, `SpikeMcpCommand.php`, `SpikeElicitationCallback.php`; test `tests/Spike/SpikeMcpCommandTest.php` (runs in `make qa` and CI).

APIs used (mcp/sdk 0.9.0): closure tool params bound by name, `Mcp\Server\RequestContext` injected by type (`Capability/Registry/InjectableParameters.php:25,48`); `InputRequiredResult(array $inputRequests, ?string $requestState)`; `ElicitRequest::forForm(string, ElicitationSchema)`; `RequestContext::mintRequestState(array)` / `getInputContext()?->elicitResult($key)`; `Builder::setRequestState($key, $ttl)`; `FileSessionStore($dir)`; `StreamableHttpTransport($psrRequest, $f, $f)` with default middleware; client `ClientCapabilities(elicitation: true, elicitationForm: true)` + `ElicitationRequestHandler`. The client resolves `input_required` automatically and `callTool()` returns the final result.

Resolved open points: DNS-rebinding middleware accepts `Host: 127.0.0.1:<port>`; the PSR-7 bridge is built directly (`PsrHttpFactory`, `HttpFoundationFactory`), no mcp-bundle services needed.

## Decision

The recipe is the basis for `lib/WorkflowMcp` (M1) and `lib/HumanInput` (M2). The requestState key is derived from `kernel.secret` (sha256, ≥ 32 bytes).

## Consequences

- Still open for M2: the deferred case (human answers later) — the client's automatic resolver answers synchronously; needs a "pending" handler or a raw first call (decision 001).
- BUGS.md: `addRequestHandler()` generic typing.
