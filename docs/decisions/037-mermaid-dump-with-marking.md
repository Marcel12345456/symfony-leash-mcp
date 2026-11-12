# 037 — Mermaid dump of a workflow with the current place highlighted

- Date: 2026-11-12
- Status: accepted
- PLAN reference: §3.7 Dashboard (`MermaidDumper`, `public/vendor/mermaid.min.js`)

## Question

How do we dump the `doc_review` workflow with the current place highlighted, and which mermaid.js file do we vendor to render it?

## Answer (symfony/workflow v8.1.8)

- `new MermaidDumper(string $transitionType, string $direction = 'LR')` (`Dumper/MermaidDumper.php:49-55`); `TRANSITION_TYPE_STATEMACHINE = 'statemachine'`, `TRANSITION_TYPE_WORKFLOW = 'workflow'` (:35-36); directions TB/TD/BT/RL/LR.
- `dump(Definition $definition, ?Marking $marking = null, array $options = []): string` (:57); `$options` is unused.
- A marked place only gets `style placeN stroke-width:4px` (:163-165, :171); no colour. Place metadata `bg_color` (static fill), `label`; transition metadata `color` → `linkStyle`.
- `doc_review` and `werewolf_game` are `state_machine` → STATEMACHINE: one edge per transition `placeA-->|"label"|placeB` (:197-209).
- Output is plain mermaid text (`graph LR` + lines). Place ids are `place0..placeN` in `$definition->getPlaces()` order (not hashed); the name map is private, so rebuild the index from `getPlaces()`.
- `https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js` (~3.5 MB) is an esbuild IIFE that sets `globalThis.mermaid`; the dumper's flowchart syntax is supported in v11. (Latest overall is 12.3.0; we pin 11.17.2.)

## Decision

Dashboard builds `new MermaidDumper(TRANSITION_TYPE_STATEMACHINE, DIRECTION_LEFT_TO_RIGHT)`, dumps with `new Marking([$status => 1])`, and appends its own `style place<i> fill:…,stroke-width:4px` line for the current place (the built-in 4px stroke is too subtle). Text goes into `<pre class="mermaid">` (Twig-escaped); refresh via `mermaid.run()`. Vendor `mermaid@11.17.2/dist/mermaid.min.js` as `public/vendor/mermaid.min.js`, served locally (no CDN in the demo).

## Consequences

- One small `WorkflowDiagram` service in `src/` (dashboard code, not lib), tested for the `style placeN` line.
- No library bug.
