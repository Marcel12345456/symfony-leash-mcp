# 003 — Symfony version

- Date: 2026-11-06
- Status: accepted
- PLAN reference: §8 stack / VERIFY "newest version supported by current `symfony/ai-*` and `mcp/sdk`"

## Question

Which Symfony version do `symfony/ai-*` and `mcp/sdk` support today?

## Answer

Composer resolver against a fresh `symfony/skeleton:8.1.*` (PHP 8.4.27):
`composer require --dry-run` resolves `symfony/ai-bundle` v0.15.0 (+ `ai-platform` v0.15.0),
`mcp/sdk` v0.9.0 and `symfony/mcp-bundle` v0.15.0 without conflicts.
Newest stable framework-bundle is v8.1.11; 8.2 is BETA only.

## Decision

Symfony 8.1 (stable, 8.1.11). Pre-release libraries pinned exactly in `composer.json`:
`symfony/ai-*` 0.15.0, `symfony/mcp-bundle` 0.15.0, `mcp/sdk` 0.9.0.

## Consequences

PLAN §8 default "7.4 LTS" not needed. README lists pinned versions. No 8.2 until it is stable and the libraries allow it.
