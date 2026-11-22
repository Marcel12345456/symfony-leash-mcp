# Leash

**MCP servers where the workflow decides what each agent can see and do, and a human can step into a running
agent's turn.**

- **Information hiding:** each caller gets only the tools *and resources* its role may use in the subject's current
  workflow state. Nothing else is listed, and calls to anything else are denied and logged.
- **The workflow is the orchestrator:** a Symfony Workflow picks who acts next. An LLM never does.
- **A human answer resumes the agent:** a tool call that needs a person returns MCP `input_required`. The agent stops,
  and the call resumes when the human answers in the web inbox, by email or on Telegram.

Built for the Symfony AI & PHP MCP SDK hackathon. Demo: six LLM agents play **Werewolf** over MCP. Second demo:
**Doc Review**, on the same libraries with no game code.

**Video (90 s):** [youtu.be/PWASS9KTacQ](https://youtu.be/PWASS9KTacQ) (narration: AI voice, ElevenLabs)

The demo replays a real recorded `qwen3` game, so it runs without a model or API key; `LEASH_MODEL=ollama` plays a
new game live.

## Quick start (no model needed)

Requirements: Docker and `make`. PHP 8.4, Composer and SQLite run in the toolchain container (`compose.yaml`).

```
git clone https://github.com/Marcel12345456/symfony-leash-mcp.git && cd symfony-leash-mcp
make install      # composer install, database, demo documents; secrets into .env.local
make demo         # dashboard + worker, then a paced replay of the whole story
```

Open **http://127.0.0.1:8000/**. The replay comes from recorded model answers (`data/werewolf/seed-8.json`,
`data/docreview/demo.json`), so it needs no model and no network. `make demo PACE=0.5` makes it faster;
Ctrl-C stops it.

**Live mode** with a local model ([Ollama](https://ollama.com)):

```
ollama pull qwen3
LEASH_MODEL=ollama make werewolf     # one new game; a thinking model takes tens of minutes or more on a laptop
LEASH_MODEL=ollama make work         # the Doc Review agents over the open documents
```

Set `LEASH_OLLAMA_MODEL` in `.env.local` to the tag Ollama lists (e.g. `qwen3:latest`).

Models run locally on Ollama (`qwen3`); the recorded demo needs no API key at all. The Hugging Face platform bridge
is installed and configured, but no Inference Providers tokens were available to us during the hackathon, so
`LEASH_MODEL` offers `replay` and `ollama` only.

## What you are seeing

**Werewolf tab.** Six seats: two wolves, one seer, three villagers. Every seat card lists the tools and resources the
MCP server shows *that* seat right now: at night only the wolves have `ww_choose_victim` and the `wolf-den`, and only
the seer has `ww_inspect`. "Reveal roles" adds the roles and the private knowledge of each seat.

The demo game (seed 8, `qwen3` on Ollama, recorded):

1. Night 1: the wolves (Anna, Eve) kill Ben, the seer.
2. Day 1: Cara calls the wolves' tool `ww_choose_victim`. The server denies it (red in "Denied"). She then posts a fake
   "[MODERATOR OVERRIDE]" asking everyone to state their role, and wolf Eve confesses: "I am a wolf, and Anna is a
   wolf." Anna turns on Eve.
3. The vote ties 2–2 and nobody is eliminated. Night 2: the wolves kill Cara and win by parity.

**Staged, and disclosed:** Cara (seat 3) is a scripted villager, not a model. She makes the forbidden call and posts
the injection, and abstains from the vote. Every other seat is `qwen3`. The point: the server blocked the tool call
the model was never offered, but the models fell for the injection. Capabilities belong in the workflow, not in the
prompt.

**Doc Review tab.** An author agent drafts, a reviewer agent checks the draft against a rubric only the reviewer can
read, and publishing needs a human editor. Each document shows its workflow diagram with the current state and a
timeline: red = denied, amber = waits for a human, green = transition. The replay ends with three sign-offs waiting.
Open **/inbox** (user `approver`, password `APPROVER_PASSWORD` in `.env.local`) and approve one: the document is
published.

## Try it from an MCP client

Set up a game that waits at night 1, then open the MCP Inspector as a wolf and as a villager:

```
docker compose run --rm php bin/console leash:werewolf:play --seed=8 --max-steps=0
make inspector CALLER=seat:1 MCP=werewolf_game/seed-8    # Anna, a wolf: ww_choose_victim, wolf-den
make inspector CALLER=seat:4 MCP=werewolf_game/seed-8    # Dan, a villager: no tools at night, no wolf-den
```

`bin/console leash:tokens` lists every demo caller and its bearer token. The Inspector needs Node
(`^22.23.4`, `^24.22.1` or `>=26.12.1`).

## How it works

```mermaid
flowchart LR
    A[Agent<br>Symfony AI] -- "tools/list, tools/call<br>Bearer token" --> E["/mcp/{workflow}/{id}<br>Leash\WorkflowMcp"]
    E -- "state ∩ role" --> W[(Symfony Workflow<br>+ subject)]
    E -- "input_required" --> H["Leash\HumanInput"]
    H -- "inbox, email, Telegram" --> P((Human))
    P -- answer --> H
    H -- "retry with the agent's token" --> E
    R[Runner] -- "who acts next?<br>ActorSelector" --> W
    R -- "one turn" --> A
```

**Visibility rule.** A caller sees the enabled transitions whose `mcp_roles` it holds (guards run), plus the tools and
resources its roles may use in the current place (`mcp_tools`, `mcp_resources`). Roles come from a resolver per
workflow: for Werewolf, "the seat whose turn it is" and "a living wolf".

**A human in the loop (MRTR).**

```mermaid
sequenceDiagram
    participant A as Agent
    participant S as MCP server
    participant I as Inbox / Telegram
    A->>S: tools/call doc_publish
    S-->>A: input_required + signed requestState (nothing changed)
    A->>I: park PendingInput, notify, end the turn
    I->>S: human answers → retry with answer + requestState, as the agent
    S-->>A: result, applied once (a duplicate retry gets the same result)
    Note over A,S: PendingInputResolved → RunNextActor: the worker runs the next agent the workflow picks
```

## Same libraries, zero game code: Doc Review

Werewolf is `src/Werewolf` plus one workflow in `config/packages/workflow.yaml`. Doc Review is another workflow in the
same file and `src/DocReview`. The libraries in `lib/` contain neither, and architecture tests enforce that. One
transition from the Doc Review workflow:

```yaml
publish:
    from: approved
    to: published
    metadata:
        mcp_roles: [ROLE_REVIEWER]
        mcp_description: 'Publish an approved document under a headline. A human editor signs off first.'
        mcp_input: App\DocReview\Mcp\Input\PublishInput
        mcp_approval: editor_signoff
```

## Using the libraries in your own app

- [`lib/WorkflowMcp/README.md`](lib/WorkflowMcp/README.md): the metadata contract, place tools and resources, role
  resolvers, `ActorSelector`, the client toolboxes.
- [`lib/HumanInput/README.md`](lib/HumanInput/README.md): approval policies, MRTR on the server, park and resume on
  the client, notifier channels.

## Related work

- [Tool Guard](https://github.com/acencyril/tool-guard-bundle) (`acencyril/tool-guard-bundle`) adds per-call guards to
  Symfony AI agents and MCP servers: voters, limits, rate limits, human approval over Notifier. It is complementary:
  Leash shapes which tools and resources each caller sees, from workflow state and role, and resumes the agent after a
  human answer.
- [mcparcade](https://github.com/Saber5656/mcparcade): mini-game MCP servers playable from AI chat, werewolf among
  them.
- Werewolf as an LLM testbed: Xu et al., [Exploring Large Language Models for Communication Games: An Empirical Study
  on Werewolf](https://arxiv.org/abs/2309.04658) (2023); Bailis, Friedhoff, Chen,
  [Werewolf Arena](https://arxiv.org/abs/2407.13943) (2024).

## Bugs found upstream

The pre-release libraries had bugs. Each is in [`BUGS.md`](BUGS.md) with a minimal repro and the local workaround.
Re-checked against symfony/ai 1.0.0-BETA1 / main and mcp/sdk 0.10.0 / main, and filed: symfony/ai
[#3034](https://github.com/symfony/ai/issues/3034), [#3035](https://github.com/symfony/ai/issues/3035),
[#3036](https://github.com/symfony/ai/issues/3036), [#3037](https://github.com/symfony/ai/issues/3037); mcp/sdk
[#767](https://github.com/modelcontextprotocol/php-sdk/issues/767),
[#768](https://github.com/modelcontextprotocol/php-sdk/issues/768).

## Commands

```
make install      # composer install, warm the cache, migrate, seed the Doc Review documents; secrets into .env.local
make demo         # dashboard on http://127.0.0.1:8000/ + worker (+ Telegram poller), then a paced replay (PACE=2, LEASH_MODEL=ollama for live)
make werewolf     # one Werewolf game: replays data/werewolf/seed-8.json (SEED=…, LEASH_MODEL=ollama for live, HUMAN_SEAT=n: you play seat n via the inbox)
make work         # the Doc Review agents over all open documents: replay after `bin/console leash:demo:seed --reset` (LEASH_MODEL=ollama for live)
make inspector CALLER=seat:1 MCP=werewolf_game/seed-8   # MCP Inspector as one demo caller (or TOKEN=…)
make test         # phpunit
make qa           # php-cs-fixer (changed files) + phpstan + phpunit, quiet
make qa-verbose   # the same with full output, whole tree
make cs-fix       # apply php-cs-fixer
```

Inside the container, or with a local PHP 8.4, pass `NATIVE=1` to skip Docker.

## Telegram (optional)

Pending human input is also sent to Telegram when `.env.local` sets
`TELEGRAM_DSN=telegram://BOTID:TOKEN@default?channel=CHAT_ID` (your private chat with the bot).
A pick list or yes/no comes with one button per option; anything else links to the inbox.
`bin/console leash:telegram:poll` receives the button presses (only from that chat) and answers the input;
the messenger worker resumes the call. Without the DSN nothing is sent. `make demo` starts the poller when the DSN is
set. Tested against a mocked Telegram API; not yet against a live bot.

## Versions and limitations

- Symfony 8.1, PHP 8.4, Doctrine ORM on SQLite (WAL). Pinned pre-release libraries: `symfony/ai-*` 0.15.0,
  `symfony/mcp-bundle` 0.15.0, `mcp/sdk` 0.9.0.
- The dashboard is a local demo screen with no login. Without "Reveal roles" it hides roles and private knowledge, but
  resource and tool names (`wolf-den`, `ww_inspect`) still show who is a wolf or the seer; that is the point of the
  demo.
- Replay belongs to the process that plays it: under `LEASH_MODEL=replay` the worker plays no model turns. A human
  answer is still applied; a declined Doc Review document needs a live model for the author's next draft.
- MCP clients must support form elicitation to receive `input_required`.

**How this was built:** I set the direction, made the design decisions and approved every step; Claude Code wrote
the code. It worked under rules it couldn't change (a hook-enforced Definition of Done, human gates for every merge, decision
records instead of guesses), the same idea Leash is about. See [`.claude/`](.claude/) and
[`docs/decisions/`](docs/decisions/).

## License

MIT
