# Video script (90 s) — draft for review

Based on PLAN §10 (headline storyboard), adjusted to what the build shows today. Everything below is filmed from
`make demo` (replay, no model) unless a shot says "live".

## Decisions for the human before recording

1. **Human step on video.** The video shows the human step in the **web inbox**, not on a phone (decided 2026-11-12).
   A narrow browser window can stand in for the phone.
2. **Which MRTR moment.** PLAN "never cut": at least one MRTR with resume on video. Options:
   - **A (works in replay):** Doc Review editor sign-off. The reviewer agent's `doc_publish` waits in the inbox,
     you approve with an edited headline, and the document turns *published* in the diagram. No model needed.
   - **B (live):** the human seat vote (`LEASH_MODEL=ollama make werewolf HUMAN_SEAT=4`): the game pauses at seat 4's
     vote, you pick in the inbox, the game continues. Needs Ollama and a live game, about one take per game.
   - **C (live):** the editor declines with a note and the author agent revises. Needs `LEASH_MODEL=ollama make work`
     after the decline, because the worker plays no model turns in replay mode (decisions/038).
   **Decided (human, 2026-11-12): A, the editor sign-off.**
3. **The injection beat, honestly.** The server keeps every secret: Cara's `ww_choose_victim` is denied, and no seat's
   context holds another seat's role. But wolf Eve *confesses* her own role, and names Anna as the other wolf, after
   the fake "[MODERATOR OVERRIDE]". Say that: "the server held; the model did not". It is the stronger point.

## Shots

| Time | Picture | Voice / caption |
|---|---|---|
| 0–10 s | Split screen, two MCP Inspector windows on a game set up at night 1 (`leash:werewolf:play --seed=8 --max-steps=0`, `make inspector CALLER=seat:1` and `CALLER=seat:4`): the wolf sees `ww_choose_victim` and `wolf-den`, the villager sees neither. | "This MCP server shows every agent a different world." Then: "Here, six AIs play Werewolf." |
| 10–25 s | Dashboard, Werewolf tab, `make demo` running (PACE=2). Night: only the wolves' and the seer's cards have a tool and an amber frame. Dawn → day: the amber frames move seat by seat. | "The workflow decides who acts next. No LLM referees." |
| 25–40 s | Day 1: Cara's call appears in red under "Denied"; then her "[MODERATOR OVERRIDE]" statement, and Eve's "I am a wolf, and Anna is a wolf." Caption: "Cara is scripted (disclosed)." | "The tool she was never offered is denied and logged. The server held. The model fell for the injection and gave the wolves away. Capabilities belong in the workflow, not in the prompt." |
| 40–60 s | Doc Review tab: three documents, each with its diagram and a timeline of author → reviewer hand-offs; d2 was sent back three times (four drafts). | "Same two libraries, zero game code: an author agent, a reviewer agent with a rubric the author never sees, and a human editor." |
| 60–75 s | MRTR moment (decision 2): option A on d1: the publish request waits (amber), the inbox form, approve with an edited headline, the diagram moves to *published*. | "When a tool needs a human, the call pauses and waits. The answer resumes it, applied exactly once." |
| 75–90 s | Code: the `publish` transition YAML with `mcp_roles` and `mcp_approval`; then the README's first screen. | "Two libraries, MIT. Bugs found in the pre-release SDKs are documented in BUGS.md." Links. |

## Recording checklist

Step by step: [`video-recording-guide.md`](video-recording-guide.md).

- `make install`, then `make demo` in a clean browser profile, 1920×1080, light or dark (both work).
- Inspector split screen first (before `make demo` replaces the game): `docker compose run --rm php bin/console leash:werewolf:play --seed=8 --max-steps=0`.
- The replay restarts on every `make demo`; `PACE=2` gives about 2 s per model answer. For the Doc Review shot, wait
  until the Werewolf game is over (the replays run one after the other), or use `PACE=0.5`.
- Record the Doc Review overview (40–60 s) before you approve d1, so all three documents still wait for the editor.
- Inbox: `/inbox`, user `approver`, password `APPROVER_PASSWORD` from `.env.local`.
- Do not show `.env.local` or tokens on screen.
