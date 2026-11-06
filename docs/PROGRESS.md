# Progress

<!-- Handoff between sessions. SessionStart injects "Current" and "Next prompt".
     Keep both short; history goes below. Updated by /phase-end. -->

## Current

- Phase: 0 — Spike & scaffold (in progress)
- Branch: m/phase-0-spike-scaffold
- Done-signal:
  - T1 **green**: `.claude/done.sh` exit 0 (incl. `make qa`); `.github/workflows/qa.yml` runs `make qa`; green `gh run list` on branch
  - T2–3 **green**: `docs/decisions/001-mcp-sdk-capabilities.md` contains a `Path: A|B|C` line
  - T4: `bin/console spike:mcp` exit 0, prints tool result, resource content, `input_required` round-trip — **green** (`decisions/008`, test in `make qa`)
  - T5 **green**: `bin/console spike:llm-tool` exit 0, prints one tool call + token usage (Ollama `qwen2.5:7b` instead of HF; `decisions/009`)
  - T6 **green**: 3 transcripts in `spikes/out/`, recommendation in `docs/decisions/002-werewolf-smoke.md`, spend $0 (local Ollama)
  - G0: human decision (evidence in Decisions)
- Blocked: —

## Next prompt

/phase 0

## Decisions

- 2026-11-06: Plan v2 (roles + gated Werewolf track) adopted; review items 1–16 applied (`docs/REVIEW.md`).
- 2026-11-06: Repo `symfony-leash-mcp`; session automation in `.claude/` (hooks, `/phase`, `/phase-end`, `/verify`).

- 2026-11-06: Symfony 8.1; `symfony/ai-*` 0.15.0, `mcp/sdk` 0.9.0 pinned (`decisions/003`).
- 2026-11-06: **G0 evidence — protocol path A proposed**: mcp/sdk 0.9.0 implements MRTR (`InputRequiredResult`, signed `requestState`, 2026-07-28, cache hints); HumanInput adds payload binding, single use, pending/notify (`decisions/001`). 
- 2026-11-06: Own per-request controller + `Server::builder()` + `StreamableHttpTransport` + persistent session store; not via `mcp.servers` (`decisions/004`).
- 2026-11-06: MCP client = `Mcp\Client` + `HttpTransport` (Bearer) + `nyholm/psr7`; explicit input/output schemas; agents re-list per step (`decisions/005`).
- 2026-11-06: Reuse ai-mcp-tool `McpToolbox`; Runner freezes tools per run → one agent run per workflow step + forwarding decorator (`decisions/006`, accepted).
- 2026-11-06: HF router chat w/ tool calling; replay via `RecordingProvider`/`Cassette`; RAG in pure-PHP `Sqlite\Store`; `MermaidDumper` (`decisions/007`).
- 2026-11-06: **G0 protocol path = A** (SDK MRTR). Human delegated the call ("you decide to reduce resources"); A is least code.
- 2026-11-06: CI (GitHub Actions `make qa`) green on `m/phase-0-spike-scaffold` (confirmed by human).
- 2026-11-06: No HF credits. Models: Ollama (local, free) for dev/smoke, replay cassettes for demo/jurors/tests; paid/free-tier cloud key only if Ollama quality fails the Werewolf gate. Hackathon allows any Symfony AI provider (PLAN §2). Werewolf G0 still open until the smoke test runs.
- 2026-11-06: **G0 evidence — Werewolf**: only `qwen3:8b` (thinking) plays coherently (seer claim, wolf deflection, votes grounded); qwen2.5 7b/14b too bland. Recommendation: go with qwen3 + replay for demo (`decisions/002`). **Awaiting human decision.**

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->
