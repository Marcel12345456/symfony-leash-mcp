# 035 — Human seat: argument-level elicit-missing step, MRTR for place tools

- Date: 2026-11-12
- Status: accepted (human, M6 design question)
- PLAN reference: §3.2 `ElicitMissingInputPolicy`, §3.5 human seat, M6

## Question

How does a human-backed caller get "exactly the missing fields" asked via `input_required`, when (a) the SDK rejects a call that lacks required fields with JSON-RPC -32602 before our handler (decisions/015), (b) `InputMapper` cannot build the DTO from `{}`, and (c) MRTR exists only for transition tools, while `ww_vote` is a place tool?

## Decision (human chose the argument-level option)

- A caller is human-backed when it has a configurable role (`ROLE_HUMAN_BACKED`). For such callers the published and registered input schema has no `required` list, so `{}` reaches our handler.
- A generic step **before** DTO mapping (`ElicitMissingInputPolicy`, lib): if the caller is human-backed and required fields of the tool's own input schema are missing from the arguments, answer `input_required` with an elicitation form for exactly those fields (taken from the tool schema; a tool may supply dynamic choices, e.g. living seats, via an optional hook). On the retry the answer is merged into the arguments, then mapping, validation and apply run as usual. Agents (not human-backed) are unaffected.
- Place tools get the same MRTR path as transition tools (bound request state, apply once per nonce, duplicate retry replays the stored result).

## Consequences

- Library change ([R]), tested with the synthetic non-domain workflow: missing fields → `input_required` naming exactly those fields; full arguments → no question; answer → applied once; duplicate retry → stored result; non-human caller with missing fields → unchanged -32602/validation path.
- Werewolf human seat uses it for `ww_vote` (enum of living seats).

## Implementation (2026-11-12)

- "Human-backed" is a **workflow role** from the workflow's role resolver (`ElicitMissingInputPolicy::HUMAN_BACKED`), not a Symfony security role, so it can vary per subject: `WerewolfSeatRoleResolver` adds it for the game's `humanSeat`. With the default `SecurityRoleResolver` it is simply a security role.
- Scope: **place tools only**. Transition tools keep their `required` list and their single MRTR round (approval); combining a missing-input round with an approval round in one call was not needed.
- The form asks for the missing *required* fields only (types from the tool schema; non-primitive types become strings). `InputChoicesInterface` (optional, on the place tool) turns a field into a `oneOf` pick list; the string answer is converted back to the schema's integer/number type before DTO mapping. Elicitation answers live under key `input` (`approval` stays for policies).
- Decline/cancel of the question → non-error result "No answer: … nothing changed." An answer that breaks the DTO constraints → the usual validation error (not stored, so the same state can be retried with a corrected answer).
- Place tool outputs are stored per nonce as `{output}` and replayed to duplicate retries.
- Werewolf: the human seat plays without a model (`HumanSeatTurnRunner`): it calls its one acting tool with `{}` through `HumanInputToolCaller`; the step returns `Paused` right after that turn (no deadline, other seats wait), and `PendingInputResolved` → `RunNextActor` continues the game. Only `ww_vote` offers choices; night tools and `ww_say` ask for plain fields.
