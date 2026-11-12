# Leash\HumanInput

A human steps into a running agent's tool call over MCP **multi-round-trip requests** (MRTR, `input_required` with a
signed `requestState`), and the agent resumes when the human answers, possibly hours later, from the web inbox, email
or Telegram.

This library does not depend on `Leash\WorkflowMcp` (architecture test); `WorkflowMcp` uses it.

## Server side: ask first, apply once

`Server\HumanInputGate` runs the MRTR steps inside one tool handler:

1. **First call:** your `Policy\ApprovalPolicyInterface::inputRequest(subject, input)` returns an `ElicitRequest`
   (or null: no question, proceed). The gate answers `input_required` and **changes nothing**.
2. **Retry:** the SDK verifies the signed `requestState` (`kernel.secret`); `RequestState\RequestBinder` checks that it
   is bound to the same subject, tool, arguments hash and caller, and not expired. Anything else is rejected.
3. **Answer:** accept → `applyAnswer(input, content)` returns the (possibly amended) input; decline / cancel → nothing
   applied. `Policy\DecliningApprovalPolicyInterface::declineReason()` turns "accepted with approve: false + note"
   into a decline with a reason.
4. **Apply once:** `Idempotency\ProcessedInputRequests` stores the result per nonce; a duplicate retry gets the stored
   result (`Doctrine\DoctrineProcessedInputRequests` in the database).

## Client side: park, notify, resume

- `Client\RawMcpClient` — one `tools/call` over HTTP with the caller's bearer token; returns `Client\InputRequired`
  or the `CallToolResult`. The SDK client resolves MRTR itself and hides `requestState`, so this one stays raw
  (decisions/017, 020, 021).
- `Client\HumanInputToolCaller` — calls a tool and hands each `input_required` to an `InputRequestHandlerInterface`.
  `Pending\DeferringInputRequestHandler` parks it as a `Pending\PendingInput` (no tokens stored), calls the
  `Pending\HumanInputNotifier` and tells the agent "paused, do not retry".
- `Pending\PendingInputAnswers::answer()` records the human's answer and dispatches `Pending\ResumePendingInput`.
- `Pending\ResumePendingInputHandler` (messenger) retries the call with the **original caller's** token
  (`Client\CallerTokens`), resolves the pending input and emits `Event\PendingInputResolved`, so your app can wake the
  agent up (the demo app dispatches its `RunNextActor`).

## Channels

`Pending\HumanInputNotifier` is the one interface a channel implements (`notify(PendingInput)`). The library ships a
logging notifier. The demo app adds email with a signed inbox link and a Telegram decorator with one button per
choice. `Inbox\ElicitationForm` turns any elicitation `requestedSchema` into a Symfony Form and back into typed
answer content, so a web inbox needs no per-question code.

## Wiring in an app

- Implement `ApprovalPolicyInterface` per approval and tag it with a name (`#[AsTaggedItem('editor_signoff')]`).
- Implement `CallerTokens` (the demo's token handler does).
- Route `ResumePendingInput` to an async messenger transport; listen to `PendingInputResolved`.
- Pick a `HumanInputNotifier` (decorate the logging one).

With `Leash\WorkflowMcp`, the metadata `mcp_approval: <name>` on a transition is all it takes.

## Tests

`tests/Unit/Lib/HumanInput/` (binding, idempotency, deferral and resume, form mapping) and the MRTR flows in
`tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php`, on a synthetic workflow.
