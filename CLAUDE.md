# CLAUDE.md — Leash

Leash is a Symfony AI & PHP MCP SDK hackathon project (deadline **Sun 2026-11-22 18:00 CET**, internal target **Sat 2026-11-21 20:00 CET**).

**Pitch:** MCP servers where the workflow decides what each agent can see and do, and a human can step into a running agent's turn (MRTR → Notifier → retry). Lead with: information hiding (scoped tools *and resources*), workflow as orchestrator, human answer resumes the agent. Main demo: Werewolf. Second: Doc Review, same libraries, no game code. Refund Desk retired (`docs/decisions/022`).

- Full plan, tracks, and gates: `docs/PLAN.md`
- Progress, deviations, test-change log: `docs/PROGRESS.md`
- Spike and design decisions: `docs/decisions/NNN-title.md`
- SDK and library bugs: `BUGS.md`

## How to work in this repo

- Follow `docs/PLAN.md` §6. One main track **M** (M1–M8); M3 (Werewolf rules) runs in parallel with M2.
- Gates G0 and G1 are **human decisions**. At a gate, stop, write the evidence into `docs/PROGRESS.md`, and wait.
- End every phase with: acceptance criteria demonstrated, `make qa` green, `docs/PROGRESS.md` updated, branch pushed, short summary.
- Anything marked **VERIFY** in the plan is an unknown. Resolve it by reading the code in `vendor/` (symfony/ai-*, mcp/sdk) or official docs, never by guessing. Record the answer in `docs/decisions/`.
- `symfony/ai` and `mcp/sdk` are pre-release. When something is broken: minimal repro, entry in `BUGS.md`, local workaround in one clearly marked class, move on. Bugs found are a deliverable.
- Prefer the smallest thing that makes the demo real.

## Session protocol (automated in `.claude/`)

- Start with `/phase <key>`; never read `docs/PLAN.md` in full (`.claude/bin/plan-section.sh <key>`).
- `vendor/` and every **VERIFY**: `/verify <question>` (subagents → `docs/decisions/`). Hook-enforced.
- Record decisions immediately (PROGRESS "Decisions" or `docs/decisions/`).
- Close with `/phase-end`, then `/clear`. Edit files in place, no copies.
- Hooks `.claude/hooks/`, tests `.claude/tests/run.sh`, pause with `touch .claude/hooks.off`.

## Library rules (non-negotiable)

- **G1:** no domain code in `lib/` (no Werewolf, no Doc Review, no refunds). Library changes are generic, documented, tested with a non-domain workflow.
- **G2:** `lib/` never depends on `App\`.
- **G3:** the workflow decides which actor works next (`ActorSelector`, counting only non-read-only tools), never an LLM.
- `Leash\WorkflowMcp\` may depend on `Leash\HumanInput\`, never the reverse.
- Architecture tests in `tests/Architecture/` enforce G1, G2 and the dependency direction.

## Unattended work rules

The machine may run for long periods without a human. In that mode:

- Work only on tasks tagged **[U]** in `docs/PLAN.md`. Tasks tagged **[R]** may be drafted but must stay on their branch, unmerged, with a "needs review" note in `docs/PROGRESS.md`.
- Branch per phase: `m/phase-N-name`, `w/phase-N-name`. Never merge to `main` and never force-push while unattended.
- Never weaken, delete, or skip an existing test, and never change an assertion to make it pass, without logging it under "Test changes" in `docs/PROGRESS.md` with the reason. Prefer fixing the code.
- Never raise spend caps. Paid model calls only through `SpendGuard`, with `LEASH_SPEND_CAP_USD` and per-command `--spend-cap` respected. Default to replay or Ollama when possible.
- Never edit `.env.local`, tokens, or CI secrets.
- If the same problem fails three attempts in a row, write a short note in `docs/PROGRESS.md` ("Blocked: …", what was tried) and switch to another [U] task.
- Commit small and often, with messages that say what and why. Keep `make qa` green on every commit. `.claude/done.sh` (runs `make qa`) is the Definition of Done; never narrow it to make it pass.
- Leave a short "Overnight summary" at the top of `docs/PROGRESS.md` at the end of each unattended stretch: done, blocked, needs review, spend used.

## Commands

```
make install      # composer install, SQLite db (WAL), migrate, seed demo; generate APP_SECRET + approver password into .env.local
make demo         # php -S + messenger worker (+ Telegram poller if TELEGRAM_DSN set), replay by default; prints dashboard URL
make werewolf     # play one Werewolf game (replay by default; LEASH_MODEL=ollama for live)
make work         # run the Doc Review agents once over all open documents
make test         # phpunit
make qa           # php-cs-fixer --dry-run + phpstan + phpunit (green before every commit)
make inspector    # MCP Inspector against the local endpoint (pass TOKEN=...)
make tournament   # Werewolf exhibition on Ollama (stretch; time-budgeted)
```

Targets arrive with their phase (PLAN v3). Keep the Makefile and README in sync with these commands.

## Code conventions

- Money is integer cents (`int`), never `float`.
- PHP 8.4, `declare(strict_types=1);` everywhere, `final` and `readonly` by default, constructor property promotion, enums over string constants, avoid `mixed`.
- PHPStan at max level (strict-rules, Symfony and Doctrine extensions). No baseline entries without a comment.
- php-cs-fixer with `@Symfony` and `@PER-CS`.
- Idiomatic Symfony: autowiring, attributes (`#[AsCommand]`, `#[AsMessageHandler]`, `#[AsEventListener]`, `#[AsTaggedItem]`), config in YAML under `config/packages/`. No container injection; typed tagged locators (`#[AutowireLocator]`) are allowed for name-based lookup (place tools/resources, approval policies, role resolvers).
- Namespaces: `Leash\WorkflowMcp\` (`lib/WorkflowMcp`), `Leash\HumanInput\` (`lib/HumanInput`), `App\` (`src/`).
- `src/Werewolf/Rules` is pure PHP: no Symfony, no Doctrine, no LLM.
- Keep it small: no abstraction without a second real use.

## MCP design rules

- Tool names `{prefix}_{action}` in snake_case (`doc_request_changes`, `ww_vote`).
- Short descriptions: what the tool does and when to use it.
- Input schemas from typed DTOs + Validator constraints; `structuredContent` with output schema where the SDK supports it.
- Errors are actionable: what went wrong, what the caller can do instead (list currently visible tools).
- Never perform an irreversible action before returning `input_required`. Retries are idempotent.

## Safety rules

- No real money moves (until removal in M6, `refund_issue` only writes a `Refund` record through a fake payment provider).
- Secrets only in `.env.local`; `.env` has placeholders.
- MRTR `requestState` is signed (SDK codec, `kernel.secret`), bound to the caller, and expires.
- The recorded Werewolf demo game contains a deliberate prompt-injection statement and a villager's forbidden `ww_choose_victim` call (PLAN §3.5). Do not "fix" them; they are the demo. (Refund seed C3 stays as it is until the Refund Desk is removed.)

## Testing

- Unit-test `lib/` (incl. a synthetic non-domain workflow); integration-test the MCP endpoint in-process; no real LLMs in tests.
- The tests listed in `docs/PLAN.md` §7 must exist before the video is recorded.
