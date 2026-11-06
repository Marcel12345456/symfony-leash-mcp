---
name: phase
description: Start work on one Leash plan phase (0, M1–M6, W1–W3) with minimal context — loads only PROGRESS.md and that PLAN.md section, names the done-signal, creates the phase branch, and sends VERIFY items to /verify. Use at the start of every session that implements plan work.
argument-hint: <0|M1..M6|W1..W3>
---

Start phase `$ARGUMENTS`. Keep the context small: do **not** read the whole `docs/PLAN.md`.

1. Run `git status -s` and `git branch --show-current`. Uncommitted changes from another phase → stop and ask.
2. Read `docs/PROGRESS.md` (sections "Current", "Decisions", "Test changes" only).
3. Run `.claude/bin/plan-section.sh $ARGUMENTS`. That output is the task list. Load an architecture section (`.claude/bin/plan-section.sh 3.2` etc.) only when a task needs it, and only that one.
4. Gates: W phases need G0 = go in PROGRESS "Decisions". If missing, stop and say so.
5. Branch: `m/phase-<n>-<slug>` for 0 and M phases, `w/phase-<n>-<slug>` for W phases (e.g. `m/phase-1-workflow-mcp`). Create it from `main` if it does not exist; otherwise check it out.
6. Write down the done-signal before coding: the acceptance criteria of this phase as commands/tests, plus `.claude/done.sh`. If a criterion is not mechanically checkable, propose a test or command for it and ask.
7. Collect every **VERIFY** in the loaded section(s). Answer them first with `/verify` (all in one go, parallel). Do not read `vendor/` yourself.
8. Update PROGRESS "Current" (phase, branch, done-signal) and commit.
9. Work task by task. Commit at every green step (`.claude/done.sh` exit 0). When a decision is made, record it right away (PROGRESS "Decisions" or `docs/decisions/`).
10. When all acceptance criteria are green: run `/phase-end`.
