# Progress

<!-- Handoff between sessions. SessionStart injects "Current" and "Next prompt".
     Keep both short; history goes below. Updated by /phase-end. -->

## Current

- Phase: M2 — HumanInput / MRTR, **in progress** [R] (branch needs human review before merge). Done: request-state primitives (`RequestBinder`, `ProcessedInputRequests` in-memory; decisions/019). Next: Doctrine nonce store + migration, `input_required`/retry on the synthetic workflow.
- Branch: m/phase-2-human-input (from main @ d43dd13).
- VERIFY done: decisions/016 (list_changed not pursued), 017 (raw client MRTR), 018 (Inspector MRTR, proposed).
- Done-signal (agreed with human): `.claude/done.sh` exit 0 incl.
  - HumanInput unit: request state sign/verify; tampered, expired, wrong caller, mismatched args rejected; nonce idempotency.
  - Synthetic workflow: first call `input_required` with no side effects; valid retry applies once; duplicate retry idempotent; decline/cancel → no change.
  - Client: `input_required` → `PendingInput` + notification; answer → retry with the original caller's token → `RunNextActor` dispatched (message class only; handler in M3).
  - Inbox: no login → redirect; signed URL works without login; expired does not; form submit dispatches resume; email contains the signed link.
  - `RefundApprovalFlowTest`: C2 `input_required` → inbox submit → retry → one `Refund`, case `refunded`; lowered amount; duplicate retry; decline stays `proposed`; `refund_revise` → `investigating`; C7 reject → `closed`.
  - Manual: Inspector 2.14.0 MRTR check, result recorded here.
- New packages for M2: symfony/messenger, form, twig-bundle, mailer.
- Blocked: none.

## Next prompt

/phase M2 — continue: Doctrine ProcessedInputRequest store + input_required/retry on the synthetic workflow

## Decisions

- 2026-11-06: Plan v2 (roles + gated Werewolf track) adopted; review items 1–16 applied (`docs/REVIEW.md`).
- 2026-11-06: Repo `symfony-leash-mcp`; session automation in `.claude/` (hooks, `/phase`, `/phase-end`, `/verify`).

- 2026-11-06: Symfony 8.1; `symfony/ai-*` 0.15.0, `mcp/sdk` 0.9.0 pinned (`decisions/003`).
- 2026-11-06: **G0 evidence — protocol path A proposed**: mcp/sdk 0.9.0 implements MRTR (`InputRequiredResult`, signed `requestState`, 2026-07-28, cache hints); HumanInput adds payload binding, single use, pending/notify (`decisions/001`). 
- 2026-11-06: Own per-request controller + `Server::builder()` + `StreamableHttpTransport` + persistent session store; not via `mcp.servers` (`decisions/004`).
- 2026-11-06: MCP client = `Mcp\Client` + `HttpTransport` (Bearer) + `nyholm/psr7`; explicit input/output schemas; agents re-list per step (`decisions/005`).
- 2026-11-06: Reuse ai-mcp-tool `McpToolbox`; Runner freezes tools per run → one agent run per workflow step + forwarding decorator (`decisions/006`, accepted).
- 2026-11-06: HF router chat w/ tool calling; replay via `RecordingProvider`/`Cassette`; RAG in pure-PHP `Sqlite\Store`; `MermaidDumper` (`decisions/007`).
- 2026-11-06: **G0 protocol path = A** (SDK MRTR). Human delegated the call ("you decide to reduce resources"); A is least code.
- 2026-11-06: CI (GitHub Actions `make qa`) green on `m/phase-0-spike-scaffold` (confirmed by human).
- 2026-11-06: No HF credits. Models: Ollama (local, free) for dev/smoke, replay cassettes for demo/jurors/tests; paid/free-tier cloud key only if Ollama quality fails the Werewolf gate. Hackathon allows any Symfony AI provider (PLAN §2). Werewolf G0 still open until the smoke test runs.
- 2026-11-06: **G0 evidence — Werewolf**: only `qwen3:8b` (thinking) plays coherently (seer claim, wolf deflection, votes grounded); qwen2.5 7b/14b too bland. Recommendation: go with qwen3 + replay for demo (`decisions/002`).
- 2026-11-06: **G0 Werewolf = go** (human): `qwen3:8b` player model, replay for demo. Role in the video: 30–45 s closer showing state ∩ role tool visibility on the same libraries; never costs M time.
- 2026-11-06: PHP `default_socket_timeout=300` in the toolchain image (`docker/php/leash.ini`): the Ollama platform ignores the configured HTTP client (BUGS.md). Ollama runs natively on the host (`OLLAMA_ENDPOINT=http://host.docker.internal:11434`).

- 2026-11-06: M1: in `investigating` the resolver sees only read-only `refund_search_policy` (§3.2 YAML wins over the M1 acceptance wording "no tools"; human decision). ActorSelector still picks the investigator.

- 2026-11-06: Invisible tool calls are answered by a per-request `CallToolRequest` handler whose `supports()` matches only non-visible names; SDK default would be JSON-RPC -32602 (`decisions/010`).
- 2026-11-06: Tools return `CallToolResult` with structuredContent `{state, availableTools, message}`; refusals via `CallToolResult::error`/`ToolCallException`; explicit `CachePolicy::default(0, Private)` (`decisions/011`).
- 2026-11-06: Workflow metadata via `getMetadataStore()` + Transition instances; roles from metadata + `RoleResolverInterface`, guards for state/voters; `ActorSelector` runs token-less (`decisions/012`).
- 2026-11-07: SchemaFactory wraps ai-platform `Contract\JsonSchema\Factory::buildProperties` + one normalizer (nullable/required, enum null, exclusiveMinimum); Serializer with COLLECT errors for input DTOs (`decisions/013`, proposed until SchemaFactoryTest pins it).
- 2026-11-07: mcp/sdk API cheat sheet; server `RequestHandlerInterface` is covariant (no ignore needed); lib tests run in-process via `buildStateless()->handleInline()` with the `_meta` envelope (`decisions/014`).
- 2026-11-07: Published inputSchema is structural only (types, enums, required, `additionalProperties: false`); Validator constraints stay server-side so violations are actionable `CallToolResult` errors, not SDK -32602. Tool closures read raw args via `RequestContext::getRequest()`; endpoint finds workflows via `#[AutowireLocator('workflow', indexAttribute: 'name')]` (`decisions/015`; 013 now accepted).
- 2026-11-07: RefundDesk M1 choices: seed adds closed history case H5 (refund of O5) so C5 is a duplicate; `issue` pays the proposed amount via `FakePaymentProvider` (`IssueRefundListener`); policy text authored in `data/policy/*.md` (keyword stub now, RAG in M3); `CaseEventLogger` ignores unmanaged subjects; `make install` now migrates and seeds.
- 2026-11-07: `/mcp/` firewall: stateless `access_token` + `StaticTokenHandler` (env tokens, `hash_equals`, empty never matches) + `BearerEntryPoint`, because the access_token authenticator sends no `WWW-Authenticate` on a missing token (RFC 6750).
- 2026-11-07: M2 list_changed not pursued: spec forbids it in a `tools/call` stream; `availableTools` in results + re-list per step (`decisions/016`).
- 2026-11-07: Client MRTR via a raw JSON-RPC client in `lib/HumanInput` (first call + resume with the original caller's token); `Mcp\Client` auto-resolves and hides `requestState`. SDK rejects bad SDK state with -32602; our payload checks (caller, argsHash, nonce) give `CallToolResult` errors; SDK state TTL raised to ≥ 24 h (`decisions/017`).
- 2026-11-07: Inspector 2.14 supports MRTR per release notes (proposed until the manual check); Inspector pinned to 2.14.0 (`decisions/018`).
- 2026-11-07: M2 creates and dispatches `RunNextActor` (message class only); the handler comes with the agents in M3 (human).
- 2026-11-07: requestState: SDK codec is the only signature; HumanInput issues/checks the bound payload read via `getInputContext()?->requestState()` (verified array); no second HMAC; key via one `RequestStateKey` helper (`decisions/019`).
- 2026-11-07: Approval wiring: transition metadata `mcp_approval: <name>` → `ApprovalPolicyInterface` (tagged, `#[AsTaggedItem]` name) via `HumanInputGate` in `TransitionToolHandler`; state bound to `<workflow>:<subjectId>` + tool name + caller; decline/cancel return a non-error result with unchanged state and visible tools. `WorkflowServerFactory::create()` takes the subject id; the controller sets the request-state key.
- 2026-11-07: Duplicate retries usually target a tool that is no longer visible, so `UnavailableCapabilityResponder` replays stored results first. It reads the verified `InputContext` from the session key `InputContext::class` (observed SDK 0.9.0 behaviour, not a documented API). Clients must declare `elicitation.form` or the SDK answers -32021 instead of `input_required` (matters for the raw client).

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->

- 2026-11-07 — M2 continued. Decision 019: SDK codec signs/verifies requestState and hands the decoded payload to the handler, so no second HMAC. `lib/HumanInput`: `RequestBinder` (issue/verify bound payload; reasons tampered/expired/wrong target/wrong caller/args mismatch), `RequestStateKey`, `ArgumentsHash`, `ProcessedInputRequests` + in-memory store; unit tests. Not yet wired into the synthetic workflow. Follow-up: tamper/wrong-key tests catch `\Throwable`; narrow to the SDK's `RequestStateException`. PROGRESS Current shortened (session-start handoff is cut at 2000 chars). Blocked: none. Needs review: M2 branch ([R]). Spend: $0.
- 2026-11-07 — M2 started. Branch from main with M1 (PR #4). VERIFY: decisions 016 (list_changed not pursued), 017 (raw client MRTR), 018 (Inspector MRTR, proposed); BUGS.md: `Client::callTool` cannot defer `input_required`; Inspector pinned 2.14.0; done-signal agreed. Blocked: none. Needs review: whole M2 branch ([R]) once built. Spend: $0.
- 2026-11-07 — M1 done. `lib/WorkflowMcp`: metadata reader, role resolvers (per-workflow override), `#[AsPlaceTool]` + registry, `ScopedCapabilityProvider`, `ActorSelector`, `SchemaFactory` (structural schemas), `InputMapper`, `TransitionToolHandler` + `PlaceToolHandler`, `UnavailableCapabilityResponder`, per-request `WorkflowServerFactory`, `WorkflowMcpController`; tests on a synthetic document workflow. `src/RefundDesk`: entities + migration, idempotent seed C1–C8 (+H5), workflow YAML, DTOs, `RefundIssueVoter`, 4 read-only place tools, `CaseEventLogger`, fake payment on `issue`. Bearer-token firewall. `RefundMcpEndpointTest` via mcp/sdk Client in-process. Decisions 013–015; BUGS.md: ai-platform JsonSchema Factory (nullable enum, draft-4 keywords). Blocked: none. Needs review: whole M1 branch ([R]). Spend: $0.
- 2026-11-06 — M1 started. Done: deps, `lib/` autoload, architecture test, decisions 010–012 (invisible-tool handler, tool results/errors/cache, workflow metadata/guards), resolver-visibility decision. Blocked: none. Needs review: M1 public API once built ([R]). Spend: $0.
- 2026-11-06 — Phase 0 done. Scaffold (Symfony 8.1, Docker toolchain, quiet `make qa`, CI green), library research (decisions 001, 003–007), Proof 1 MCP round-trip (008, in `make qa`), Proof 2 agent tool call on Ollama (009), Werewolf smoke on 3 local models (002). G0: path A, Werewolf go. BUGS.md: 3 confirmed (SDK handler generics, Ollama total tokens, Ollama client timeout) + 2 candidates. Blocked: none (HF credits unavailable → Ollama + replay). Needs review: whole phase-0 branch ([R]). Spend: $0. Note: vendor-guard hook blocks commands that combine `rm … vendor` with `grep` (false positive; split the command).
