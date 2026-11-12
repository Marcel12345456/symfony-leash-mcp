# 036 — Doc Review workflow, editor sign-off, decline with a note

- Date: 2026-11-12
- Status: proposed (needs the human's OK on "decline with a note")
- PLAN reference: §3.6 Doc Review, M6 acceptance (`DocReviewFlowTest`)

## Question

How does Doc Review map onto WorkflowMcp, and how does "decline with note → `doc_send_back` visible → author gets the note" work, when an MCP elicitation `decline` carries no content and the HumanInput gate drops a declined call without a trace?

## Proposal

Workflow `doc_review` (state machine, prefix `doc`, subject `App\DocReview\Entity\Document`, roles `ROLE_AUTHOR`, `ROLE_REVIEWER` via agent users `agent:author`, `agent:reviewer`):

| Place | Tools (role) | Resources (role) |
|---|---|---|
| drafting | `doc_submit_draft {title, body}` → in_review (author) | brief, draft, feedback (author) |
| in_review | `doc_request_changes {feedback}` → drafting, `doc_approve` → approved (reviewer) | brief, draft (reviewer), **rubric (reviewer only)** |
| approved | `doc_publish {headline}` → published, `mcp_approval: editor_signoff` (reviewer); `doc_send_back` → drafting, guard: an editor note is open (reviewer) | brief, draft (reviewer) |
| published | — | draft (both) |

Editor sign-off form `EditorSignoff { bool $approve; ?string $headline; ?string $note }`; `applyAnswer()` puts an edited headline into the publish input.

**Decline with a note** = the editor *accepts* the form with `approve: false` and a note (MCP's own decline has no content). Generic library addition: an optional `DecliningApprovalPolicyInterface::declineReason(array $content): ?string`; non-null → the gate returns `Declined` with that reason, the tool result says "Not approved: <reason>; nothing changed", and WorkflowMcp dispatches `ApprovalDeclined(workflow, subject, caller, tool, reason)`. The app stores the reason as an open editor note on the document (once per nonce); `doc_send_back` becomes visible and moves the note into the author's feedback.

Agents: `leash:work` runs author and reviewer turns over all open documents (workflow picks the actor, G3); tool calls go through `HumanInputToolCaller`, so `input_required` defers to the inbox and the run ends ("a human has been asked").

## Consequences

- Library: `DecliningApprovalPolicyInterface` + `ApprovalDeclined` event, tested on the synthetic workflow ([R]).
- App: `src/DocReview` (entities + migration, seed D1–D3, workflow YAML, resources, `EditorSignoffPolicy`, prompts, `leash:work`), `DocReviewFlowTest`.
- Refund Desk removal after Doc Review is green (tag `refund-desk-v1` first).
