# 039 — Related work links (README §9 item 8)

- Date: 2026-11-12
- Status: accepted (links opened; wording constraints below)
- PLAN reference: §9 README skeleton, item 8 "Related work" (VERIFY)

## Question

Which related-work links are real, and what may the README say about them?

## Answer (pages opened 2026-11-12)

1. **Tool Guard** — https://github.com/acencyril/tool-guard-bundle, https://packagist.org/packages/acencyril/tool-guard-bundle.
   Symfony bundle, MIT, v0.3.0 (2026-11-08), PHP 8.2+, Symfony 7.3/8.0, tagline "A prompt is not a lock."
   Rules in code per tool call: `#[Granted]` (Security voters), `#[Limit]`, `#[RateLimit]`, `#[RequiresApproval]`
   (human approval over Notifier). Same rules for Symfony AI agents, MCP servers and other agent loops; audit journal,
   kill switch. Its README says it does not shape what the model says, filter outputs or undo completed actions.
   Not stated there either way: per-caller tool lists, resuming the agent after approval (not checked in its source).
2. **mcparcade** — https://github.com/Saber5656/mcparcade: "A collection of mini-game MCP servers playable from AI
   chat, including shogi, werewolf, and escape games." Per-seat hiding and license not checked. A GitHub search for
   werewolf/mafia MCP servers found nothing else relevant (shallow search).
3. **Xu et al. 2023** — https://arxiv.org/abs/2309.04658, "Exploring Large Language Models for Communication Games:
   An Empirical Study on Werewolf" (Yuzhuang Xu, Shuo Wang, Peng Li, Fuwen Luo, Xiaolong Wang, Weidong Liu, Yang Liu).
4. **Werewolf Arena 2024** — https://arxiv.org/abs/2407.13943 (Suma Bailis, Jane Friedhoff, Feiyang Chen). Copy the
   exact title from the page when writing the README; the organisation was not shown, so it is not named.

## Decision

README item 8 gets these four links. Tool Guard: "complementary: per-call guards (voters, limits, rate limits,
Notifier approval) for Symfony AI and MCP. Leash shapes which tools and resources each caller sees, from workflow
state and role, and resumes the agent after a human answer (MRTR)." Describe what Leash does; never what the others
cannot do. mcparcade and the papers: one neutral line each, no comparison.

## Consequences

- Never claim "first", "only" or "no other Werewolf MCP server exists".
- AvalonBench and other benchmarks stay out (not opened).
