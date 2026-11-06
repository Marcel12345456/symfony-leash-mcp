---
name: verify
description: Resolve one or more Leash VERIFY questions by delegating each to the verify-researcher subagent in parallel and writing the answers as decision records in docs/decisions/. Use for any question that needs reading vendor/ code or library docs, instead of reading them in the main session.
argument-hint: "<question>[; <question> ...]"
---

Research `$ARGUMENTS`. Several questions are separated by `;`.

1. Split into questions. For each, name the PLAN section it comes from (search `docs/PLAN.md` for the VERIFY text with `grep -n`, do not read the whole file).
2. Launch one `verify-researcher` subagent per question, **all in a single message** so they run in parallel. Pass the question, the PLAN reference, and today's date. Do not read `vendor/` yourself.
3. For each result: next free number `NNN` (`ls docs/decisions`), write `docs/decisions/NNN-<slug>.md` from the returned draft (Status: accepted if the answer is unambiguous, otherwise proposed and ask the user).
4. In `docs/PLAN.md`, replace that `**VERIFY**` marker with `(verified: decisions/NNN)`. Change PLAN content only when the decision requires it, and say so.
5. Add one line per decision to PROGRESS "Decisions". If a library bug was found, add an entry to `BUGS.md` (format: PLAN §8).
6. Commit. Reply with one line per question: answer in ≤ 20 words + file link.
