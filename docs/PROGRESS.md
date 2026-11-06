# Progress

<!-- Handoff between sessions. SessionStart injects "Current" and "Next prompt".
     Keep both short; history goes below. Updated by /phase-end. -->

## Current

- Phase: 0 — Spike & scaffold (in progress)
- Branch: m/phase-0-spike-scaffold
- Done-signal:
  - T1: `.claude/done.sh` exit 0 (incl. `make qa`); `.github/workflows/qa.yml` runs `make qa`; green `gh run list` on branch
  - T2–3: `docs/decisions/001-mcp-sdk-capabilities.md` contains a `Path: A|B|C` line
  - T4: `bin/console spike:mcp` exit 0, prints tool result, resource content, `input_required` round-trip
  - T5: `bin/console spike:hf-tool` exit 0, prints one tool call + token usage
  - T6: 3 transcripts in `spikes/out/`, recommendation in `docs/decisions/002-werewolf-smoke.md`, spend ≤ $1
  - G0: human decision (evidence in Decisions)
- Blocked: T5, T6 — HF coupon reports "fully used"; no HF credits yet (2026-11-06).

## Next prompt

/phase 0

## Decisions

- 2026-11-06: Plan v2 (roles + gated Werewolf track) adopted; review items 1–16 applied (`docs/REVIEW.md`).
- 2026-11-06: Repo `symfony-leash-mcp`; session automation in `.claude/` (hooks, `/phase`, `/phase-end`, `/verify`).

- 2026-11-06: Symfony 8.1; `symfony/ai-*` 0.15.0, `mcp/sdk` 0.9.0 pinned (`decisions/003`).
- 2026-11-06: **G0 evidence — protocol path A proposed**: mcp/sdk 0.9.0 implements MRTR (`InputRequiredResult`, signed `requestState`, 2026-07-28, cache hints); HumanInput adds payload binding, single use, pending/notify (`decisions/001`). Awaiting human decision.
- 2026-11-06: Own per-request controller + `Server::builder()` + `StreamableHttpTransport` + persistent session store; not via `mcp.servers` (`decisions/004`).
- 2026-11-06: MCP client = `Mcp\Client` + `HttpTransport` (Bearer) + `nyholm/psr7`; explicit input/output schemas; agents re-list per step (`decisions/005`).
- 2026-11-06: Reuse ai-mcp-tool `McpToolbox`; Runner freezes tools per run → one agent run per workflow step + forwarding decorator — **proposed, needs OK** (`decisions/006`).
- 2026-11-06: HF router chat w/ tool calling; replay via `RecordingProvider`/`Cassette`; RAG in pure-PHP `Sqlite\Store`; `MermaidDumper` (`decisions/007`).

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->
