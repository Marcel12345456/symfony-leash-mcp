# 038 — `make demo` without a model: paced Werewolf replay, Doc Review cassette

- Date: 2026-11-12
- Status: accepted (human chose paced replay and a recorded Doc Review cassette)
- PLAN reference: M7, §3.7

## Context

`make demo` must tell the story with no model. Werewolf already replays (decisions/033), but the dashboard polls
every second and a replay finishes in about two seconds. Doc Review had no replay.

## Decision

- **Pacing:** `ReplayPlatform` waits `LEASH_REPLAY_PACE` seconds (default 0; `make demo` sets it) before each recorded
  answer. That paces every turn, like a model that thinks, and the runner stays unchanged.
- **One cassette format for both demos:** the key of an interaction is `int|string`. Werewolf keeps seat numbers
  (`seed-8.json` is unchanged); Doc Review uses `<role>:<document>` from the turn message
  ("You are the author. Document d1." → `author:d1`), so each agent per document has its own FIFO.
- **Prompt hash per agent set:** `ModelChoiceFactory::promptHash($agents)` hashes `config/prompts/<set>/*.md` in name
  order; for the one Werewolf file this equals the old hash.
- **`leash:work --record [--cassette]`**, default cassette `data/docreview/demo.json`, recorded from freshly seeded
  documents (`leash:demo:seed`). Unused answers at the end → failure (the run diverged).
- **Worker in replay mode plays no model turns:** a replay's position lives in the process that plays it, so
  `RunNextActorHandler` logs and returns when `LEASH_MODEL=replay`. A human's answer is still applied by the resume
  (`ResumePendingInputHandler`), so an editor's approval in the demo still publishes; a decline sends the document
  back, and the author's next turn needs a live model.

## Consequences

- `ReplayPlatformTest` covers per-agent replay and pacing; `RunNextActorHandlerTest` covers the replay skip.
- The demo human seat (Werewolf `--human-seat`) needs a live model; it is not part of the replayed demo.
