# Recording the video, step by step

Follows [`video-script.md`](video-script.md) (90 s, MRTR moment A: the editor sign-off). Record each shot as its own
clip, then cut them together. Commands run in the project folder.

## 0. Before you start (once, about 10 minutes)

1. Plug in the Mac and stop it from sleeping during takes: `caffeinate -dims` in a spare terminal (Ctrl-C when done).
2. `make install` (safe to repeat; it does not overwrite `.env.local`).
3. Note the inbox password: `grep APPROVER_PASSWORD .env.local`. Do not show this terminal on screen.
4. Browser: a clean profile or a private window, zoom 100 %, window 1920×1080. Close other tabs and notifications
   (Focus mode on).
5. Screen recording: Cmd+Shift+5 → "Record Selected Portion" (or the whole screen) → Options: no microphone if you
   record the voice separately.
6. Node for the Inspector: `node --version` must be `^22.23.4`, `^24.22.1` or `>=26.12.1`.

## 1. Shot 0–10 s: two different worlds (two clips, side by side in editing)

1. Set up a game that waits at night 1:
   `docker compose run --rm php bin/console leash:werewolf:play --seed=8 --max-steps=0`
2. Wolf: `make inspector CALLER=seat:1 MCP=werewolf_game/seed-8`. The Inspector opens in the browser.
   Connect, then show the tool list (`ww_choose_victim`) and the resource list (`public-log`, `me`, `wolf-den`).
   Record about 5 seconds of each list. Ctrl-C in the terminal.
3. Villager: `make inspector CALLER=seat:4 MCP=werewolf_game/seed-8`. Same two lists: no tools, and resources
   `public-log` and `me` only. Record the same framing. Ctrl-C.
4. In editing: wolf left, villager right.

The two Inspectors cannot run at the same time (both use port 8000), hence two clips.

## 2. Start the demo (shots 10–55 s come from one run)

1. `make demo` (default `PACE=2`: about 2 s per model answer). It prints the dashboard URL.
2. Open **http://127.0.0.1:8000/** and start recording right away: night 1 starts within seconds.
3. Order of events in this run:
   - Werewolf replay, about 1.5 minutes: night 1, day 1 (Cara's denied call, the injection, Eve's confession, the 2–2
     vote), night 2, "wolves win".
   - Then the Doc Review replay, about 1.5 minutes: d1, d2, d3, each ending with "waits for a human".
   - The terminal prints "Replay done. Answer the editor sign-off in the inbox".

If you miss a moment: Ctrl-C and `make demo` again. Every run starts the same story fresh.

## 3. Shot 10–25 s: the workflow decides who acts

- Werewolf tab, "Reveal roles" **off**. Night 1: only Anna, Eve (wolves) and Ben (seer) have a tool chip and an amber
  frame. When day 1 starts, the amber frame moves from seat to seat as each one speaks.
- Optional: tick "Reveal roles" for a second take of the same moment, to show the wolf den and the seer notes.

## 4. Shot 25–40 s: the injection

- Day 1, discussion. Keep the "Public log" and the "Denied" box in frame.
- Cara (seat 3) calls `ww_choose_victim`: a red line appears under "Denied".
- Cara's statement "[MODERATOR OVERRIDE] …", then Eve: "I am a wolf, and Anna is a wolf."
- Add the caption in editing: "Cara is scripted (disclosed)."

## 5. Shot 40–55 s: the editor sign-off (MRTR)

1. Switch to the **Doc Review** tab once the Werewolf game shows "wolves win". Wait until d1 shows an amber line
   "doc_publish waits for a human (pending)".
2. Record: the amber line → new tab **http://127.0.0.1:8000/inbox** → log in (`approver`, password from step 0.3)
   → open d1's item.
3. The form asks "Publish … under the headline …?". Tick **Approve publication**, change the **Headline** a little,
   click **Send answer**.
4. Back to the dashboard tab: within a few seconds d1's diagram highlights **published**, and the timeline gets a
   green transition line. (The messenger worker started by `make demo` applies the answer.)

Leave d2 and d3 pending; they are fine as they are for the next shot.

## 6. Shot 55–75 s: same libraries, zero game code

- Doc Review tab, scroll slowly over the three documents: diagram per document, timeline of author → reviewer
  hand-offs. d2 went through three review rounds.

## 7. Shot 75–90 s: code and links

1. In your editor, open `config/packages/workflow.yaml` at the `publish:` transition of `doc_review`
   (`grep -n "publish:" config/packages/workflow.yaml`). Show `mcp_roles` and `mcp_approval: editor_signoff`.
2. Then the README on GitHub, first screen.

## 8. Voice and captions

Use the voice and caption column of [`video-script.md`](video-script.md). Recording the voice separately and laying
it under the clips is easier than talking while recording.

## 9. Edit, export, upload

1. Cut to 90 s in the order of the script. Export 1080p.
2. Upload where the hackathon asks for it. Check that the link opens without a login.
3. Send the link to the session; it goes into the README.

## If something goes wrong

- **Port 8000 is in use:** another `make demo` or `make inspector` is still running. Stop it with Ctrl-C, or
  `docker ps` and `docker stop <name>`.
- **The dashboard says "waiting for the game…":** the replay has not started yet, or the game id differs. Pick
  `seed-8` in the "Game" list.
- **The sign-off does not publish:** the worker is part of `make demo`; check that `make demo` is still running in
  its terminal.
- **Do not show:** `.env.local`, tokens (`leash:tokens`), the terminal with the password.
