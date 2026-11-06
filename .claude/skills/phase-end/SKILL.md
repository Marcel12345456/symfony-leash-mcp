---
name: phase-end
description: Close a Leash phase or work stretch — run the done-check, write the PROGRESS.md handoff (current state, decisions, next prompt), commit, push the phase branch, and tell the user to /clear. Use when a phase's acceptance criteria are met, before a long break, or when the context-size reminder appears.
argument-hint: "[note]"
---

Close the current stretch of work. `$ARGUMENTS` is an optional note for the log.

1. Run `.claude/done.sh`. If it fails, do **not** continue: report the failure honestly, record it under "Current → Blocked" in `docs/PROGRESS.md`, commit that, and stop.
2. Check that every decision made in this session is written down (PROGRESS "Decisions" or `docs/decisions/NNN-*.md`). Add missing ones now.
3. Update `docs/PROGRESS.md`:
   - **Current:** phase status (done / in progress + what is left), branch, done-signal, blocked.
   - **Next prompt:** the exact first message for the next session, e.g. `/phase M2` or `/phase M1 — continue with SchemaFactory`. A fresh session must be able to start from this line alone.
   - **Log:** one entry: date, done, blocked, needs review ([R] items), spend used.
   - **Test changes:** any weakened/changed test, with reason.
4. Commit. The subject must start with `Phase end:` (e.g. `Phase end: M1 done, provider + handler green`); the body says what and why. The `phase-boundary` hook keys on that prefix.
5. Push the phase branch: `git push -u origin <branch>`. Never push or merge `main`; merging is a human decision. If the push fails, say so and leave it.
6. End your reply with exactly:
   `Phase boundary. Run /clear, then: <Next prompt>`
