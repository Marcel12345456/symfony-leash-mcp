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

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->
