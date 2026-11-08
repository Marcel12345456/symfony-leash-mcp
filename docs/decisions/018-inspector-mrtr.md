# 018 — MCP Inspector and the 2026-07-28 MRTR flow

- Date: 2026-11-07
- Status: proposed (until the manual M2 Inspector check confirms it)
- PLAN reference: M2 acceptance "If MCP Inspector supports MRTR rounds, the flow works interactively from Inspector"

## Question

Does the current MCP Inspector render `input_required` elicitations and retry with `inputResponses` + `requestState`, and what does our server do if it falls back to the legacy wire?

## Answer

From release notes and PRs, not run.

- Latest Inspector: 2.14.0 (2026-11-04), https://github.com/modelcontextprotocol/inspector/releases; TS SDK 2.x, "works on 2025-11-25 and 2026-07-28".
- MRTR support since v2.0.0 (PR #1719): on a modern connection it drives the loop itself (`requestWithInputRequired`), shows the elicitation modal with an "input_required" badge, retries with `inputResponses` + echoed `requestState` on a fresh id, max 10 rounds. Fixes: #1705, #1860 (2.3.0), #2610 (rotating `requestState`, 2.10.0).
- Not established: how Inspector picks modern vs legacy.
- Legacy wire in mcp/sdk 0.9.0: `Server/InputRequiredShim.php:32-50` turns a returned `InputRequiredResult` into live `elicitation/create` requests and re-enters the handler from the top (8 rounds `:57`, 600 s per answer `:60`). Needs the client's elicitation capability and an open stream.
- Inspector 2.x needs Node ^22.23.4, ^24.22.1 or >=26.12.1 (already guarded in `Makefile`).

## Decision

Run the M2 manual Inspector check with a pinned Inspector 2.14.0, record the negotiated era and whether the modal appeared in PROGRESS. Both eras require the handler to be idempotent and re-derive its position on every entry.

## Consequences

- `Makefile`: pin `@modelcontextprotocol/inspector@2.14.0`.
- Note for M2: a deferred human approval does not fit the Inspector flow (it answers live in the modal); the Inspector check exercises the synchronous accept/decline path.
- No BUGS.md entry.

## Manual check, part 1 (human, 2026-11-08)

- `make inspector TOKEN=<investigator>` against `/mcp/refund_case/C1`: Inspector 2.14.0 UI connects, Tools tab lists `refund_start_investigation`. Inspector `--cli --method tools/list` returns the same.
- With the **resolver** token the UI showed only the Servers tab: capabilities `{logging, completions}`, no `tools`. In state `received` the resolver sees no tool, and the server then omits the `tools` capability, so the Inspector hides the tab. Follow-up (M4, `lib/`): always advertise `tools` so an empty list is shown instead.
- UI GET for the standalone SSE stream gets 405 (allowed by the spec); the UI keeps working.
- The refund workflow has no `mcp_approval` transition, so MRTR can only be shown on the spike endpoint `/mcp-spike/{id}` (no auth). **Part 2 open:** in the Inspector UI add server `http://127.0.0.1:8000/mcp-spike/demo` (Streamable HTTP, no header), run its tool, expect the approval form, accept, see the result. (Opening that URL in a browser returns JSON-RPC 405 "Method Not Allowed"; that is expected.)
