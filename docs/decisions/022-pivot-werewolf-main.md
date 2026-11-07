# 022 — Pivot: Werewolf main demo, Doc Review second, Refund Desk retired

- Date: 2026-11-07
- Status: accepted (human)
- PLAN reference: §1, §3.5, §3.6, §6 (plan v3)

## Question

Another hackathon entry, Tool Guard (`acencyril/tool-guard-bundle`, project page "Tool Guard — a prompt is not a lock"), covers the refund-guard story. Does Leash keep the Refund Desk as its main demo?

## Evidence

From Tool Guard's public README (Packagist/GitHub, read 2026-11-07; the hackathon page needs a login):

- Tagline: "Your AI agent can be talked into refunding 640 €. Tool Guard stops it, in code." Demo domain: order refunds.
- Calls are refused at execution time (Symfony voters, `#[Granted]`, separate user and agent rights); tools stay visible.
- Held calls return at once; a human approves via admin page, email, ntfy or Notifier chat channels; "approve runs the tool once, with the arguments shown". The agent does not resume. No MRTR, elicitation or `input_required`.
- No workflow orchestration, no multi-agent setup, no hidden information.
- Polished: no-key demo, ~90 s video, profiler panel, mcp/sdk fixes merged upstream (#640–643).

Our Refund Desk (C3 injection, phone approval) would read as a copy.

## Decision

- **Werewolf becomes the headline demo**, framed by the mechanism, not the game: information hiding via state ∩ role scoped tools *and resources*, the workflow as orchestrator, a human answer resuming the agent via MRTR.
- **Doc Review** (author/reviewer agents, rubric visible only to reviewers, human editor sign-off with resume) replaces the Refund Desk as the second, non-game domain that proves the libraries are generic.
- **Refund Desk is retired** in M6: tagged `refund-desk-v1`, removed with its tests once Doc Review covers the same library paths; removals logged under "Test changes".
- **RAG is dropped** (it only served the refund policy search). Submission tags: MCP, Agents, Human-in-the-loop.
- **Gate G1 (Fri 11-13)** is now the fallback switch: if recorded `qwen3:8b` games are dull, Doc Review headlines and Werewolf becomes a short segment.
- The README names Tool Guard as related, complementary work.

## Consequences

- Libraries (`lib/`) unchanged in direction; M1/M2 work carries over. Place resources and per-caller actor selection move earlier (M4).
- M2 drops `RefundApprovalFlowTest`; its round-trip coverage moves to `DocReviewFlowTest` (M6). The synthetic `ApprovalFlowTest` already covers the server side.
- New early risk check: `qwen3:8b` tool calling through the Symfony AI Ollama bridge (Proof 2 used `qwen2.5:7b`).
- `.claude/tests/bin.t.sh` asserted the refund YAML in PLAN §3.2; it now asserts `doc_review:` (logged in PROGRESS "Test changes").
