# 019 — Reading back the MRTR requestState payload

- Date: 2026-11-07
- Status: accepted
- PLAN reference: §3.3 HumanInput server side steps 3-4; builds on decisions/001, 014, 017

## Question

On an MRTR retry, how does a tool handler read back the payload given to `RequestContext::mintRequestState(array)`, and is there a public codec API?

## Answer

mcp/sdk v0.9.0.

- `InputContext::requestState(): array` returns the already verified inner `data` array (`Server/Stateless/InputContext.php:121-124`); path `$ctx->getInputContext()?->requestState()`, null context on a first call (`Server/RequestContext.php:108-114`).
- The SDK verifies MAC and `exp` before the handler runs and passes only `data` (`Server/Stateless/StatelessProtocol.php:900-927`); SDK `exp` is not exposed.
- Wire format `base64url(json{exp,data}).base64url(HMAC-SHA256)` (`RequestStateCodec.php:21,51-58`): signed, **not encrypted** — readable by the client.
- Public `final` codec: `new RequestStateCodec(string $key, int $ttlSeconds = 600)` (key ≥ 32 bytes, else `InvalidArgumentException`, :31-45); `mint(array, ?int $now): string`; `verify(string, ?int $now): array` throws `RequestStateException` (`malformed` / `mac` / `expired`).
- Payload is JSON round-tripped: scalars, null, nested arrays; objects come back as arrays; no size limit. Key `_mcp.answers` is reserved (`ElicitationReplay.php:54`).
- No codec configured: `mintRequestState` throws `Mcp\Exception\LogicException` (`RequestContext.php:151-157`); a retry with a state is rejected as -32602 / HTTP 400 (`StatelessProtocol.php:505-511, 920-922`).
- `buildStateless()->handleInline()` passes the codec through (`Builder.php:1016`), so in-process tests cover the retry path.

## Decision

- No second HMAC layer in `lib/HumanInput`: the SDK codec is the signature. HumanInput owns the **binding**: it issues `{subjectRef, capability, argsHash, callerId, nonce, expiresAt}` (minted via `mintRequestState`) and, on retry, checks the verified array against the current call (target, caller, `argsHash`, own `expiresAt`). Mismatches → actionable `CallToolResult` errors (017). A malformed payload shape → "tampered".
- Tampered/SDK-expired states stay SDK rejections (-32602); unit tests pin this with `RequestStateCodec` directly.
- One key-derivation helper (`RequestStateKey::fromSecret(kernel.secret)`) shared by server wiring and tests; SDK TTL ≥ our `expiresAt` (86400).
- Payload holds only refs and hashes, no secrets or raw arguments.

## Consequences

- PLAN §3.3 step 3: "HMAC-signed" is done by the SDK codec; step 4 reads the payload via `getInputContext()?->requestState()`.
- A missing key fails only at call time; the server factory should set the key unconditionally.
- No bug; no BUGS.md entry.
