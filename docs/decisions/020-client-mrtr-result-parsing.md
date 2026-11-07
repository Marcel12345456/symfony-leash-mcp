# 020 — Client side MRTR: parsing results, storing asks, building answers

- Date: 2026-11-07
- Status: accepted
- PLAN reference: §3.3 HumanInput "Client side" (`InputRequiredResult`, `PendingInput`); decisions/017

## Question

How does a raw decoded JSON-RPC `result` become `InputRequiredResult` / `CallToolResult`, what do `ElicitRequest` and `inputResponses` entries look like, and what can `PendingInput` store and rebuild losslessly?

## Answer

mcp/sdk v0.9.0 (paths under `vendor/mcp/sdk/src/`).

- **`InputRequiredResult` has no `fromArray`** (`Schema/Result/InputRequiredResult.php:32-86`): ctor `(array $inputRequests = [], ?string $requestState = null)` (`:40-43`), values `ElicitRequest|CreateSamplingMessageRequest|ListRootsRequest`; throws if both are empty (`:45-47`). Wire: `{resultType:"input_required", inputRequests?:{key:{method, params}}, requestState?}` (`:57-85`); each ask is a bare `{method, params}` pair (`:64-75`).
- **`CallToolResult::fromArray(array $data): self`** (`Schema/Result/CallToolResult.php:92`) throws `InvalidArgumentException` on missing/invalid `content`, unknown content `type`, wrong `isError`/`_meta` type (`:94-121`); ignores `resultType`; passes `structuredContent` through unvalidated (`:126`). Public readonly: `content`, `isError`, `structuredContent`, `meta` (`:49-54`).
- **Dispatch on `resultType`:** only `Client\Stateless\InputRequestResolver::asked(array $result): ?array` (`Client/Stateless/InputRequestResolver.php:72-79`): raw `inputRequests` when `resultType === 'input_required'`, else null; absent means complete (`:66-68`).
- **`ElicitRequest`** (`Schema/Request/ElicitRequest.php:38-43`): `(string $message, ?ElicitationSchema $requestedSchema = null, ElicitationMode $mode = Form, ?string $url = null)`. Form params on the wire: `{message, requestedSchema}` (no `mode` key, `:130-133`). `ElicitRequest::fromArray` needs the full envelope `{jsonrpc:"2.0", id, method, params}` (`Schema/JsonRpc/Request.php:28-47`); `getParams()` is protected.
- **One `inputResponses` entry** = `ElicitResult::jsonSerialize()`: `{action:"accept"|"decline"|"cancel", content?:{…}}` (`Schema/Result/ElicitResult.php:77-88`); ctor `new ElicitResult(ElicitAction $action, ?array $content = null)` (`:23-26`), `ElicitAction` Accept/Decline/Cancel (`Schema/Enum/ElicitAction.php:19-24`). The server parses entries with `ElicitResult::fromArray($data, $mode)`, which requires `content` on a form accept (`:48-51`); malformed entries count as unanswered and the server asks again (`Server/Stateless/InputContext.php:51-53, 100-110`). `inputResponses` must encode as a JSON object (`Client/Protocol.php:596-601`); `requestState` is echoed byte for byte (`:604-608`).

## Decision

- Leash keeps its own client-side `InputRequired` value object (raw `inputRequests` array + `requestState` string). The `resultType` check is inlined (three lines) rather than calling `InputRequestResolver::asked()` from the SDK's client internals. Otherwise the client uses `CallToolResult::fromArray($result)`.
- `PendingInput` stores `inputRequests` as the raw wire JSON (`{key:{method, params}}`) and `requestState` as a string. No SDK object round-trip.
- To render a form, wrap a pair as `{jsonrpc:'2.0', id:<key>, method, params}` and call `ElicitRequest::fromArray`.
- Answers use `new ElicitResult(ElicitAction::…, $content)->jsonSerialize()` under the same key; the retry sends `(object) $map` plus the unchanged `requestState`. An accept always carries `content` (even `[]`).

## Consequences

- PLAN §3.3 "`InputRequiredResult` value object … parsed raw if the SDK lacks it" applies: the SDK has no `fromArray`.
- Pitfalls covered in tests: `inputResponses` as an object; content on accept.
- No SDK bug.
