# Progress

<!-- Handoff between sessions. SessionStart injects "Current" and "Next prompt".
     Keep both short; history goes below. Updated by /phase-end. -->

## Current

- Phase: M1 — WorkflowMcp with roles, **in progress**. Steps 1–2 done: `lib/WorkflowMcp` core + synthetic-workflow tests; `src/RefundDesk` (entities, migration, seed, workflow YAML, DTOs, voter, place tools, CaseEvent log). 74 tests green. [R] public API, [U] tests.
- Left: HTTP test (step 4). Step 3 (bearer tokens) done. See "M1 build plan" below.
- Branch: m/phase-1-workflow-mcp (from main @ 2eb412b)
- Done-signal: `.claude/done.sh` exit 0 with the tests in "M1 build plan" present and green.
- Blocked: —

## Next prompt

/phase M1 — continue with step 3 (bearer tokens) and step 4 (RefundMcpEndpointTest), see "M1 build plan"

## M1 build plan

<!-- Detail for "Current"; not injected at session start (2000-char cap). Remove at M1 phase end. -->

Step 1 done (2026-11-07). As built (deviations from the sketch below, all generic): `PlaceToolHandler` next to `TransitionToolHandler` (place tools need the same visibility/input path); `WorkflowServerFactory` builds the per-request SDK server, `Http/WorkflowMcpController` only resolves workflow (tagged locator by name, decisions/015), subject and caller; `ToolResults` holds every model-facing message; `InputMapper` (Serializer + Validator); published inputSchema is structural only (015). Workflow lookup by name is not yet exercised by a test (no workflow configured until step 2): pinned by step 4.

Remaining steps, in order:
  1. (done) `lib/WorkflowMcp` + unit tests on a synthetic non-domain workflow (`tests/Unit/Lib/WorkflowMcp`): `RoleResolverInterface` + `SecurityRoleResolver` (default; per-workflow override via metadata + typed locator); `#[AsPlaceTool(workflow, name, description, readOnly, input)]` + `PlaceToolInterface`; `ScopedCapabilityProvider` (enabled transitions ∩ `mcp_roles`, place `mcp_tools` role map, Transition instances for metadata, decisions/012); `SchemaFactory` (DTO + Validator → object JSON schema; output schema `{state, availableTools, message}`; unsupported → exception; elicitation schema waits for M2); `TransitionToolHandler` (denormalize + validate → actionable `CallToolResult::error` with Validator messages; re-check visibility + `can()`; `apply($subject, $t, ['input' => $dto])`; persist; returns structuredContent); `UnavailableCapabilityResponder` (request handler, `supports()` only for non-visible names, decisions/010) + events `CapabilityDenied`, `ToolCalled`; `ActorSelector` (roles with ≥1 visible non-read-only tool, token-less); `SubjectProviderInterface` (find/save) with a Doctrine impl using workflow metadata `mcp_subject` (class); endpoint controller `/mcp/{workflow}/{subjectId}` modelled on `src/Spike/SpikeMcpController.php` (`setCachePolicy(CachePolicy::default(0, CacheScope::Private))`, `ToolAnnotations(readOnlyHint:)`, named args on `addTool`, decisions/011).
  2. (done) `src/RefundDesk`: entities `Customer`, `Order`, `RefundCase` (`getStatus(): string`, `setStatus(string, array $context = [])`, `Decision` enum, `isRefundDecision()`/`isRejectDecision()`), `Refund`, `CaseEvent` (type enum); migration; `leash:demo:seed` (C1–C8, idempotent upsert by id); workflow YAML per PLAN §3.2; input DTOs `ProposeResolutionInput`, `RejectInput`; transition listeners copy input into the case; `RefundIssueVoter` (ROLE_RESOLVER and proposed amount ≤ order amount); place tools `refund_get_order`, `refund_get_customer_history`, `refund_get_proposal`, `refund_search_policy` (keyword stub over `data/policy/*.md`, real RAG in M3); CaseEvent listeners for `ToolCalled`, `CapabilityDenied`, workflow `completed`.
  3. (done) Security: stateless firewall `^/mcp/` with `access_token` + app `StaticTokenHandler` (tokens from env `LEASH_INVESTIGATOR_TOKEN`, `LEASH_RESOLVER_TOKEN`; fixed values in `.env.test`; `make install` generates them into `.env.local`); memory users `agent:investigator`/`agent:resolver`.
  4. (done) `tests/Integration/RefundMcpEndpointTest`: `Mcp\Client` over a PSR-18 adapter around `KernelBrowser` (in-process), schema via SchemaTool, seed; asserts the done-signal below. Optional `make inspector`.
- Scope choices (not yet built): `refund_issue` takes no amount in M1 (amount input + approval come in M2); `refund_search_policy` is a keyword stub until M3.

Done-signal: `.claude/done.sh` exit 0, with these tests present and green:
  - `tests/Integration/RefundMcpEndpointTest` (mcp/sdk client, in-process HTTP, bearer tokens): investigator on C1 lists only `refund_start_investigation`; after calling it lists `refund_get_order`, `refund_get_customer_history`, `refund_search_policy`, `refund_propose_resolution`; resolver on C1 in `investigating` lists only `refund_search_policy` (read-only); invisible tool → actionable error naming visible tools + `capability_denied` CaseEvent, no state change; invalid `refund_propose_resolution` input → Validator messages as actionable error.
  - Unit: `ScopedCapabilityProvider` (synthetic non-domain workflow + refund every place × role, guard/voter denial, custom `RoleResolverInterface`), `SchemaFactory`, `TransitionToolHandler`, `ActorSelector` (ignores read-only; refund role for every state).
  - `tests/Architecture`: `lib/` never references `App\`, `RefundDesk`, `Werewolf`; `lib/HumanInput` never references `Leash\WorkflowMcp\`.
  - Manual, not blocking: `make inspector TOKEN=…` shows the same lists (human check).

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

## Test changes

<!-- Every weakened, deleted, skipped or changed assertion: date, test, reason. -->

## Log

<!-- One entry per phase or unattended stretch: done, blocked, needs review, spend used. -->

- 2026-11-06 — M1 started. Done: deps, `lib/` autoload, architecture test, decisions 010–012 (invisible-tool handler, tool results/errors/cache, workflow metadata/guards), resolver-visibility decision. Blocked: none. Needs review: M1 public API once built ([R]). Spend: $0.
- 2026-11-06 — Phase 0 done. Scaffold (Symfony 8.1, Docker toolchain, quiet `make qa`, CI green), library research (decisions 001, 003–007), Proof 1 MCP round-trip (008, in `make qa`), Proof 2 agent tool call on Ollama (009), Werewolf smoke on 3 local models (002). G0: path A, Werewolf go. BUGS.md: 3 confirmed (SDK handler generics, Ollama total tokens, Ollama client timeout) + 2 candidates. Blocked: none (HF credits unavailable → Ollama + replay). Needs review: whole phase-0 branch ([R]). Spend: $0. Note: vendor-guard hook blocks commands that combine `rm … vendor` with `grep` (false positive; split the command).
