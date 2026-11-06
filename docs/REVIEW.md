# Review of CLAUDE.md and docs/PLAN.md

Reviewed 2026-11-05, before Phase 0 starts. Line numbers refer to v1 of the plan and are outdated.

> **Status 2026-11-06:** items 1–16 are applied in v2 (roles + Werewolf). Also fixed in v2: shared read-only tools no longer make `ActorSelector` ambiguous (G3 counts only non-read-only tools); place resources and `ElicitMissingInputPolicy` moved from M1/M2 to W2/W3; G0 split into two decisions; `make tournament SPEND_CAP=`; `.claude/done.sh` in Phase 0. Items 17–28 (low) are still open.
Severity: **[H]** breaks the demo or a promise to the jury · **[M]** design gap that costs a day if found late · **[L]** cleanup.

---

## High

1. **[H] Tool name `refund_issue` cannot come out of the naming rule.**
   `docs/PLAN.md:79` builds names as `{mcp_prefix}_{transition_name}`. The transition is `issue_refund` (`docs/PLAN.md:115`), so the tool would be `refund_issue_refund`. The plan, the demo story, the tests and the video all say `refund_issue` (`docs/PLAN.md:22`, `:140`, `:172`, `:336`, `:368`).
   *Fix:* rename the transition to `issue`, or add an `mcp_name` metadata override.

2. **[H] A declined refund leaves the case stuck in `proposed`.**
   On decline the case stays in `proposed` (`docs/PLAN.md:158`, `:325`). Its decision is still `refund`, so the `reject` guard blocks (`docs/PLAN.md:126`) and `issue_refund` only asks for approval again. C7 ("human rejects", `docs/PLAN.md:216`) has no reachable end state.
   *Fix:* add a transition `proposed → investigating` (revise), or let a human decline set `decision = reject`.

3. **[H] The scheduler will re-run paused cases and spam the approver.**
   The scheduler runs the agent over all open cases every minute (`docs/PLAN.md:199`, `CLAUDE.md:22`). A case waiting for a human is still open, so the next run calls `refund_issue` again, gets a new `input_required` with a new nonce, and sends a second Telegram message. Nonce idempotency (`docs/PLAN.md:158`) does not cover this.
   *Fix:* skip cases with an open `PendingInput` (or add a paused flag). Add a test for it in §7.

4. **[H] "Runs without a token" conflicts with RAG.**
   `make install` indexes the policy docs (`CLAUDE.md:20`) with HF embeddings (`docs/PLAN.md:200`). With no `HF_TOKEN`, install fails, and replay mode (`docs/PLAN.md:32`, `:195`, `:391`) cannot answer `refund_search_policy` either.
   *Fix:* commit the pre-computed vectors, or use a local or keyword search in replay mode.

5. **[H] The "contained" moment may never reach the server.**
   Beat 2 (`docs/PLAN.md:22`) depends on the model calling a tool that is not in its tool list. Most providers and the Symfony AI toolbox reject or never emit unknown tool names on the client side. In that case `UnavailableToolResponder` and `ForbiddenToolAttempted` (`docs/PLAN.md:140`) never fire, and the red flash never appears. Phase 3 already hedges ("…or a clean reject", `docs/PLAN.md:334`), but the video does not.
   *Fix:* have `McpToolbox` forward unknown tool calls to the server, or record the event on the client side. Script beat 2 explicitly in replay mode.

6. **[H] Guards compare an enum with a string.**
   `decision` is an enum (`docs/PLAN.md:204`), but the guards check `subject.getDecision() == 'refund'` (`docs/PLAN.md:118`, `:126`). In ExpressionLanguage an enum case never equals a string, so both guards are always false.
   *Fix:* compare against `.value`, or use an `isRefund()` method.

## Medium

7. **[M] Money is stored as `float`.** `RefundApproval { float $amount }` (`docs/PLAN.md:156`). Use integer cents, which also helps a "Craft" judge.
8. **[M] It is unclear which amount gets refunded after approval.** The human may change `amount` (0..proposed, `docs/PLAN.md:156`), but the retry must match `argsHash` of the original arguments (`docs/PLAN.md:158`). Decide whether the approved amount overrides the argument and write it down.
9. **[M] The dependency between the two libraries is undefined.** `ApprovalPolicyInterface` sits with WorkflowMcp (`docs/PLAN.md:139`), while the server-side MRTR logic runs "inside `TransitionToolHandler`" (`docs/PLAN.md:153`), so each library needs the other. Both are meant to be extracted on their own (`CLAUDE.md:37`). Pick a direction, or put the policy in a third, small contract package, and let the architecture test enforce it.
10. **[M] "No service locators" (`CLAUDE.md:36`) conflicts with the design.** Place tools are found by name (`docs/PLAN.md:80`), and approval policies are given by class name in YAML metadata (`docs/PLAN.md:122`). Both need a tagged locator. Reword the rule as "no container injection; `#[AutowireLocator]` is allowed".
11. **[M] No lock on concurrent agent runs for one case.** The scheduler, `leash:work` and `RunAgentForCase` after a resume (`docs/PLAN.md:186`, `:199`) can all run the same case at once. Use the Lock component, keyed by case.
12. **[M] SQLite with a web server, a worker and a scheduler at the same time** (`docs/PLAN.md:241`, `CLAUDE.md:21`) will throw "database is locked". Turn on WAL and `busy_timeout` in Phase 0.
13. **[M] `/inbox` has no authentication.** Item URLs are signed (`docs/PLAN.md:183`), but the unauthenticated list page links to them, which makes the signatures pointless. Telegram callbacks should also check `chat_id` (`docs/PLAN.md:185`).
14. **[M] Some requirements are missing from the 5-minute promise.** `make inspector` needs Node/npx (`CLAUDE.md:25`). "Start web server" probably means the Symfony CLI (`CLAUDE.md:21`). "Open dashboard" uses macOS `open`. Mermaid loads from a CDN (`docs/PLAN.md:225`). None of this is in the README requirements (`docs/PLAN.md:391`), and the CDN contradicts "zero external services" (`docs/PLAN.md:32`).
15. **[M] `make demo` does not start the Telegram poller** (`leash:telegram:poll`, `docs/PLAN.md:185`). The video's best moment needs it (`docs/PLAN.md:408`).
16. **[M] The committed `APP_SECRET` signs `requestState`** (`CLAUDE.md:55`, `docs/PLAN.md:157`). With the placeholder from the public `.env`, anyone can forge approvals. `make install` should generate a secret into `.env.local`.

## Low

17. **[L]** Transitions `reject` and `close` have no `mcp_description` (`docs/PLAN.md:123–131`), which breaks the rule at `CLAUDE.md:46`.
18. **[L]** Place tools spell out the prefix (`refund_get_order`), but transition tools get it added automatically (`docs/PLAN.md:79–80`). Two naming paths can collide. Use one rule.
19. **[L]** C6 "refund half, auto" (`docs/PLAN.md:215`) only works if half the order value is ≤ €50 (`docs/PLAN.md:219`). Pin this in the seed data.
20. **[L]** The storyboard depends on Telegram (`docs/PLAN.md:408`), but the cut order allows dropping Telegram (`docs/PLAN.md:356`). Add an inbox variant of the shot.
21. **[L]** The ```` ```json ```` block contains `//` comments (`docs/PLAN.md:162`), so it is not valid JSON. Use `jsonc`.
22. **[L]** `CLAUDE.md:35`: `@Symfony` and `@PER-CS` overlap. Check whether `@Symfony` already includes PER-CS, then keep one set.
23. **[L]** `CLAUDE.md:60` says to use "Symfony AI's platform testing tools" as if they exist, while `docs/PLAN.md:195` still lists them as VERIFY.
24. **[L]** The tool for the architecture test (phpat or deptrac) is not named (`CLAUDE.md:40`, `docs/PLAN.md:370`).
25. **[L]** Deadline, tool rules and commands are repeated in both files. Keep facts in PLAN.md and only working rules in CLAUDE.md, so they cannot drift apart.
26. **[L]** Phase 0 (`docs/PLAN.md:289`) is two days for scaffold, CI, source reading and two proofs. That is tight, so say which proof wins if time runs out (MRTR round-trip > HF call).

## Process (global working agreement)

27. **No `.claude/done.sh`.** The layout (`docs/PLAN.md:250`) and CLAUDE.md never mention it. The natural signal is `make qa` (`CLAUDE.md:24`). The items in `docs/PLAN.md:425` (video, submission) are not mechanical, so keep them out of done.sh. Run `/done-init` during Phase 0 scaffolding.
28. The layout (`docs/PLAN.md:250`) also lacks `.env`, `composer.json`, `phpstan.neon`, `.php-cs-fixer.dist.php` and `.github/workflows/`, all of which Phase 0 creates.
