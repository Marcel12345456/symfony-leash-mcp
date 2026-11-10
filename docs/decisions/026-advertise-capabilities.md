# 026 — Always advertise `tools` and `resources` (Builder::setCapabilities)

- Date: 2026-11-10
- Status: accepted
- PLAN reference: §3.2 WorkflowMcp (per-request server); follow-up to decisions/018 (Inspector hid the Tools tab)

## Question

How does mcp/sdk 0.9.0 decide the advertised server capabilities (legacy `initialize`, stateless discover), and how do we force `tools` and `resources` when the caller's registry is empty?

## Answer

- `Builder.php:1103`: `$this->serverCapabilities ?? $this->detectCapabilities(...)`. An explicit `setCapabilities()` (`:418`) replaces detection completely.
- Detection (`Builder.php:1159-1195`): `tools: $registry->hasTools()` (`:1169`), false for an empty registry (the 018 symptom); `resources: hasResources() || hasResourceTemplates()` (`:1165`); `logging` and `completions` true (`:1174-1175`); `resourcesSubscribe` true when resources exist (`:1166`).
- `ServerCapabilities` constructor defaults (`src/Schema/ServerCapabilities.php:37-50`): tools, resources, prompts true; all listChanged and subscribe false; **logging and completions false**. An explicit setting must pass `logging: true, completions: true` to keep them.
- Serialization (`ServerCapabilities.php:182-215`) emits a key when its flag or a sub-flag is true; `prompts: false` to omit prompts.
- Legacy `initialize` applies `withoutListChanged()` (`InitializeHandler.php:56`); stateless discover passes capabilities as given (`StatelessProtocol.php:438-443`), so keep listChanged false ourselves.
- Elicitation/MRTR are client capabilities; nothing to set on the server. `enableExtension()` extensions are merged into an explicit setting (`Builder.php:1107-1109`).

## Decision

`WorkflowServerFactory` always calls `setCapabilities(new ServerCapabilities(tools: true, toolsListChanged: false, resources: true, resourcesSubscribe: false, resourcesListChanged: false, prompts: false, promptsListChanged: false, logging: true, completions: true))`. Same capabilities for every caller; leaks nothing about visibility; listChanged stays false (decisions/016).

## Consequences

- One call in `WorkflowServerFactory`. Test: a caller with zero visible tools still gets `capabilities.tools` and `capabilities.resources`, and `tools/list` returns an empty list.
- No BUGS.md entry (public API). Pitfall: bare `new ServerCapabilities()` drops logging/completions.
