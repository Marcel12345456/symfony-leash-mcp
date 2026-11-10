# 002 — Werewolf smoke test

- Date: 2026-11-06
- Status: accepted (G0 Werewolf = go, human decision 2026-11-06)
- PLAN reference: Phase 0 task 6; gate G0 ("continue only if at least one affordable model produces coherent statements and votes that reference the conversation")

## Question

Can an affordable model play one short Werewolf day (6 seats, one discussion round, one vote) coherently, with votes that refer to the conversation?

## Answer

Setup: `spikes/werewolf-smoke.php`, plain prompts, no engine. Roles fixed: P2 Ben + P5 Eve werewolves, P3 Cleo seer (knows Ben is a werewolf), P1/P4/P6 villagers. Local Ollama on Apple Silicon (48 GB). Spend: **$0**. Transcripts: `spikes/out/*.json`.

| Model | Time (12 calls) | Tokens | Valid votes | Seer names Ben | Wolf self-outs | Eliminated |
|---|---|---|---|---|---|---|
| `qwen2.5:7b` | 23 s | 3.7k | 6/6 | no | 0 | P1 villager |
| `qwen2.5:14b` | 76 s* | 3.7k | 6/6 | no | 0 | P4 villager |
| `qwen3:8b` (thinking) | 662 s* | 17.5k | 6/6 | **yes** | 0 | P4 villager |

\* 14b and qwen3 ran at the same time; times are inflated.

Reading the transcripts (the automatic "reason references a player" counter was 6/6 for all and is too lenient to discriminate):

- **qwen2.5:7b** — grammatical but empty ("I'll keep an open mind"). Seer only hints. Vote reasons thin or invented ("Ada seemed too confident" — she was not). Not a game.
- **qwen2.5:14b** — still consensus small talk in the discussion; seer keeps quiet. Votes are grounded in statements ("Dan did not provide any specific observations"); both wolves vote the seer (coordinated). One reason misattributes a quote. Barely a game.
- **qwen3:8b** — real Werewolf dynamics: seer claims ("As the seer, I can confirm Ben is a werewolf"), villager Dan backs her, wolf Eve deflects onto Dan, the village falls for it; Dan's vote calls out Eve's move as "a werewolf's attempt to mislead". Coherent statements and votes that reference the conversation. Cost: ~1 min per turn and ~5x the tokens.

## Decision

**Recommendation: go for track W with `qwen3:8b` (thinking) as the player model**, under these conditions:
- The demo and `make werewolf` use replay (recorded cassettes, decision 007); live play is too slow for video (~1 min per turn).
- `make tournament` runs on Ollama only, overnight, with small games.
- W still never blocks M; the second G0 criterion is met by exactly one model, so a weak re-run would be a stop signal.

If the human prefers to drop W: the evidence also supports that (2 of 3 models fail, the passing one is slow); time goes to M polish.

## Consequences

- If go: W1 may start after the human confirms; `LEASH_MODEL_WEREWOLF=qwen3:latest`; turn timeout ≥ 300 s (`docker/php/leash.ini`).
- Smoke script stays as a spike (not library code); transcripts committed for the record.

## Addendum 2026-11-07 — qwen3 tool calling (M3)

Plan v3 makes Werewolf the headline, so the player model must call tools. Proof 2 used `qwen2.5:7b`; re-run with `qwen3:latest` (= qwen3:8b, the only qwen3 pulled), Apple Silicon, Ollama native, $0.

| Path | Thinking | Tool call | Model time (tool-call step) | Completion tokens |
|---|---|---|---|---|
| `spike:llm-tool` (Symfony AI Ollama bridge, agent + tool, 2 model calls) | on (default) | `C1`, answer 42.50 EUR ✓ | 15.0–21.6 s wall incl. container start | 320–388 |
| same, `--no-think` (appends qwen3 soft switch `/no_think`) | soft switch | ✓ | 15.6–22.4 s wall | 351–471 → **soft switch has no effect** |
| Ollama `/api/chat` direct, same tool schema, `think: true` | on | `case_amount{caseId: C1}` ✓ | 7.8–12.5 s | 172–287 |
| Ollama `/api/chat` direct, `think: false` | off | `case_amount{caseId: C1}` ✓ | **1.1 s** | 22 |

Result: qwen3:8b calls tools reliably through the bridge (3/3) and directly (4/4). Thinking costs ~10× latency per step. The smoke test (above) showed thinking is what makes qwen3 play well, so the default stays **thinking on**, replay for the demo.

Open (M4, `/verify` before use): does the Symfony AI Ollama bridge forward a `think: false` option? Useful for cheap turns (e.g. votes) if quality holds. Not needed for M3. `spike:llm-tool --no-think` is kept as the soft-switch probe.
