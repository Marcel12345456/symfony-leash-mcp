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
