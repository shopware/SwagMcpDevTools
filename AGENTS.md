# SwagMcpDevTools — Agent Guide

> **⚠️ Experimental.** Proof of concept. API, tool names, parameters, and the
> bundle's packaging model may all change. Do not build dependent tooling against
> these interfaces yet.

## Purpose

This bundle provides developer-facing MCP diagnostic tools for Shopware. Its tools sit on top of the platform primitives in core and offer read-only log introspection that a developer would run against a remote instance — staging, production, SaaS environments — that the laptop-side `ai-coding-tools` cannot reach.

## What belongs here

- Developer/operator diagnostics: log streaming, log search, background operation notifications, (future) cache stats, (future) queue depth, (future) cron health
- Introspection of runtime state that lives on the Shopware host (filesystem, process state)
- Tools that need disk access, OPcache, or other host-level resources the core tools intentionally don't touch
- Event subscribers that write lightweight completion signals to existing Shopware entities (e.g. `notification`) for consumption by the MCP tools above

## What does NOT belong here

- Merchant operator workflows (order management, product creation, analytics) — see `SwagMcpMerchantAssistant`
- Platform primitives (entity CRUD, schema, aggregations, system config) — stay in core
- Business-logic mutations or workflow side-effects. Event subscribers may write to existing notification/log entities, but must not mutate business data
- Local developer tooling (PHPStan, lint, build scripts) — lives in `shopwareLabs/ai-coding-tools`

## Tool naming

All tools use the `swag-dev-tools-` prefix:

```
swag-dev-tools-log-stream
swag-dev-tools-log-search
swag-dev-tools-notifications
```

Never use `shopware-` (reserved for core) or `merchant-` (reserved for SwagMcpMerchantAssistant).

## Portability

The bundle supports three installation layouts and the tooling resolves paths at runtime:

1. **Monorepo** — bundle at `custom/bundles/SwagMcpDevTools/`, Shopware core at `../../../src/Core`
2. **Composer-installed** — bundle at `vendor/swag/mcp-dev-tools/`, Shopware core at `vendor/shopware/core`
3. **Standalone clone** — bundle cloned standalone, Shopware core in the bundle's own `vendor/shopware/core`

Composer scripts use bare binary names (`phpstan`, `phpunit`, `php-cs-fixer`) — Composer adds `vendor/bin` to `PATH` before running scripts, so path discovery of executables is automatic across all three layouts. The `phpstan` script runs `bin/generate-phpstan-config.php` first, which uses `Composer\InstalledVersions::getInstallPath('shopware/core')` (with a monorepo fallback to `src/Core`) to render `phpstan.neon` from `phpstan.neon.dist` — PHPStan's NEON parser does not expand `%env()%` inside `includes:`, so substitution at runtime is the portable alternative.

`tests/TestBootstrap.php` uses the same `InstalledVersions` lookup (with a monorepo fallback) to locate `shopware/core/TestBootstrapper.php`.

No runtime bundle code contains hard-coded paths — only tooling does.

## Registration

Tag services with `shopware.mcp.tool` in `src/Resources/config/services.xml`. `McpToolCompilerPass` in core handles DI wiring and MCP server registration automatically — no `scan_dirs` entry needed.

Feature-flag guard: each tool service carries `<tag name="shopware.feature" flag="MCP_SERVER"/>`. `FeatureFlagCompilerPass` removes tools when the flag is off, so the bundle itself does not need a `Feature::has()` check in `build()` (that would run too early, before the flag registry has been populated).

## Coding patterns

- Extend `McpToolResponse` (core) — provides `$this->success()`, `$this->error()`, and the 100 KB response guard
- MCP **tools** in this bundle are read-only. No `dryRun`, no write paths, no transactions inside tool invocations
- **Event subscribers** may write to existing Shopware entities (currently `notification` via `NotificationService`) to persist signals for the tools to read. Use `Context::createDefaultContext()` — `NotificationService::createNotification()` elevates to system scope internally
- For tools that need to stream progress during long-running waits, declare `RequestContext $context` as the first `__invoke` parameter — the MCP SDK injects it automatically via type hint. Call `$context->getClientGateway()->progress(float, ?float, string)` to send SSE progress notifications; it silently no-ops if the client did not send a `progressToken`
- No ACL privilege check. Shopware has no dedicated "read server logs" privilege, and reusing an entity privilege like `log_entry:read` (DAL table, not filesystem) would be semantically wrong. Access is gated by MCP authentication + the per-integration allowlist. Do not inject `McpContextProvider` unless a future tool genuinely needs the `Context`
- No `#[McpToolRequires]` attributes — there are no ACL privileges to declare for these tools. If a future tool in this bundle does use `requirePrivilege()`, add the corresponding `#[McpToolRequires]` to keep the Admin UI coverage warning accurate
- Do not read arbitrary paths. Always resolve files inside `%kernel.logs_dir%` and enforce an allowlisted extension (`.log`). Use `basename()` to strip traversal segments
- Redact sensitive fields aggressively. Field-name redaction (normalized camelCase → snake_case, whole-token match) + value-shape redaction (`Bearer ...`, JWTs, `SW[IU]A...` integration keys). Truncate long string values to 300 chars
- Cap response sizes. Tail-read from the end of the file, apply limits server-side before serializing

## Tests

Unit tests live in `tests/unit/`. Subdirectories mirror `src/` (`Tool/`, `Event/`). Do not use integration test infrastructure — unit tests only.

- **Tool tests** — mock `EntityRepository` and `RequestContext`/`ClientGateway`. For file-based tools, create a temp log directory in `setUp()`, write fake Monolog lines, invoke the tool, assert on the JSON response
- **Event subscriber tests** — mock `NotificationService`, construct events with mocked entities, call the handler directly, assert `createNotification` was called with expected params
- Pattern assertions for redaction go through a `#[DataProvider]` with positive + negative cases (`password` → redact; `monkey` → pass)
- The `wait=true` path can be tested without real sleeps: use `timeout=0` to force immediate timeout (loop body never executes), or mock the repository to return data on the first call so the tool returns before any `sleep()`

## Security layers

Requests to dev-tools pass through two layers (the third — ACL — is intentionally skipped; see "Coding patterns" above):

1. **Authentication** — credentials from `.mcp.json` (`sw-access-key` + `sw-secret-access-key`)
2. **Per-integration allowlist** — tool must be enabled in Settings → Integrations → Edit MCP Tools; error: `Tool "X" is not in the allowlist for this integration. Enable it under Settings → Integrations → Edit MCP Tools.`

See `src/Core/Framework/Mcp/docs/security.md` for the full layer reference and troubleshooting table.

## Core conventions

General tool patterns, response format, ACL, `McpEntityIncludes`, and registration mechanics are documented in core:

- [`src/Core/Framework/Mcp/AGENTS.md`](../../../src/Core/Framework/Mcp/AGENTS.md) — architecture overview
- [`src/Core/Framework/Mcp/Tool/AGENTS.md`](../../../src/Core/Framework/Mcp/Tool/AGENTS.md) — tool authoring rules
- [`src/Core/Framework/Mcp/docs/extensibility.md`](../../../src/Core/Framework/Mcp/docs/extensibility.md) — plugin/bundle registration walkthrough
