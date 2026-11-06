# 012 — Workflow metadata, guards, method marking store

- Date: 2026-11-06
- Status: accepted
- PLAN reference: §3.2 Visibility rule, Metadata contract

## Question

How do we read workflow/place/transition metadata, does nested YAML survive, how are guards evaluated, and what does the method marking store need? (symfony/workflow 8.1.8, framework-bundle 8.1.11, security-bundle 8.1.11)

## Answer

Paths under `vendor/symfony`.

- `$workflow->getMetadataStore()` (`workflow/Workflow.php:279`): `getWorkflowMetadata()`, `getPlaceMetadata(string)`, `getTransitionMetadata(Transition)`, `getMetadata(key, subject)` (`workflow/Metadata/MetadataStoreInterface.php:23-38`). Transition metadata is keyed by **object identity**: use Transition instances from `getDefinition()->getTransitions()` or `getEnabledTransitions()`.
- All metadata nodes are `normalizeKeys(false)` + variable prototypes (`framework-bundle/DependencyInjection/Configuration.php:560-566, 687-704`): underscore keys and nested role lists survive.
- `guard:` → `GuardListener` on `workflow.<name>.guard.<transition>` with token storage, authorization checker, role hierarchy, validator; variables `subject`, `user`, `token`, `role_names`; functions `is_granted`, `is_valid`. Without a token: no exception; `is_granted` runs against a `NullToken`, so the voter normally denies.
- `can()`, `buildTransitionBlockerList()`, `getEnabledTransitions()` all run guards. State machine transition names are unique only per from-place.
- Method marking store (state machine): `getStatus(): string` and `setStatus(string $status, array $context = []): void`.

## Decision

- YAML metadata as planned (§3.2). Visibility is computed from `getEnabledTransitions()` so guards apply; metadata looked up by Transition instance.
- Roles live in `mcp_roles` / `mcp_tools` and are evaluated by `RoleResolverInterface`; guards express subject state and voters. MCP requests run behind the stateless `access_token` firewall, so `is_granted` sees the agent's token.
- `ActorSelector` runs without a caller (CLI/worker): `issue` is then hidden by `REFUND_ISSUE` under a `NullToken`; the resolver is still selected in `proposed` via `revise`/`reject`. Accepted; revisit if a workflow's only acting transition is voter-guarded.

## Consequences

- Unit tests with the synthetic workflow: guard blocks a transition tool; same-name transitions from different places.
- No bug found.
