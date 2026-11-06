# Progress

<!-- Handoff between sessions. SessionStart injects "Current" and "Next prompt".
     Keep both short; history goes below. Updated by /phase-end. -->

## Current

- Phase: 0 — Spike & scaffold **done** (all T1–T6 green, G0 decided: path A, Werewolf go). Phase 0 is [R]: branch `m/phase-0-spike-scaffold` pushed, **needs review + merge to main by a human** before M1 branches from main.
- Branch: m/phase-0-spike-scaffold
- Done-signal: `.claude/done.sh` exit 0 (automation tests + `make qa`: cs-fixer, phpstan max, phpunit incl. Proof 1 over real HTTP)
- Blocked: —

## Next prompt

/phase M1

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
- 2026-11-06: **G0 evidence — Werewolf**: only `qwen3:8b` (thinking) plays coherently (seer claim, wolf deflection, votes grounded); qwen2.5 7b/14b too bland. Recommendation: go with qwen3 + replay for demo (`decisions/002`).
- 2026-11-06: **G0 Werewolf = go** (human): `qwen3:8b` player model, replay for demo. Role in the video: 30–45 s closer showing state ∩ role tool visibility on the same libraries; never costs M time.
- 2026-11-06: PHP `default_socket_timeout=300` in the toolchain image (`docker/php/leash.ini`): the Ollama platform ignores the configured HTTP client (BUGS.md). Ollama runs natively on the host (`OLLAMA_ENDPOINT=http://host.docker.internal:11434`).

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->

- 2026-11-06 — Phase 0 done. Scaffold (Symfony 8.1, Docker toolchain, quiet `make qa`, CI green), library research (decisions 001, 003–007), Proof 1 MCP round-trip (008, in `make qa`), Proof 2 agent tool call on Ollama (009), Werewolf smoke on 3 local models (002). G0: path A, Werewolf go. BUGS.md: 3 confirmed (SDK handler generics, Ollama total tokens, Ollama client timeout) + 2 candidates. Blocked: none (HF credits unavailable → Ollama + replay). Needs review: whole phase-0 branch ([R]). Spend: $0. Note: vendor-guard hook blocks commands that combine `rm … vendor` with `grep` (false positive; split the command).
