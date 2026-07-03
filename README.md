# SwagMcpDevTools

Developer-oriented MCP tools for Shopware. Installs as a Symfony bundle alongside
the core MCP server and extends its `/api/_mcp` endpoint with two things:

1. **Diagnostics** — read-only log streaming/search and background-operation
   notifications, so an agent can triage a running instance (including remote
   SaaS/PaaS/staging environments the laptop-side `ai-coding-tools` can't reach).
2. **Scaffolding** — a single code-generation tool (`swag-dev-tools-scaffold`) that
   returns Shopware-accurate instructions for creating extension artifacts (plugins,
   entities, endpoints, admin UI, …) the right way, without the MCP server ever
   writing to disk.

> [!WARNING]
> **Experimental. May be removed at any time.** This bundle is a proof of concept and is **not** covered by any stability or backwards-compatibility guarantee. It can be discontinued, archived, or removed without notice, particularly in favor of **Shopware Copilot**, which is the official, supported direction for AI-assisted workflows. API, tool names, and parameter shapes may change without notice. Do not rely on it for production-critical processes.

## Requirements

- Shopware 6.7.11.0+ (experimental; stable target 6.8)
- Installed via Composer (`composer require swag/mcp-dev-tools`) — the bundle is loaded behind an `InstalledVersions::isInstalled('swag/mcp-dev-tools')` guard in `config/bundles.php`, so it is only active when Composer knows the package (see [Installation](#installation))
- `MCP_SERVER=1` in `.env`
- A valid MCP integration (credentials in your client config)
- The tools enabled for that integration under Settings → Integrations → Edit MCP Tools

## Installation

As a Symfony bundle (not a Shopware plugin). **Composer must know the package** —
Shopware's `config/bundles.php` loads the bundle behind an
`InstalledVersions::isInstalled('swag/mcp-dev-tools')` guard, so if Composer has
never installed it, the bundle (and all its MCP tools/prompts) is silently
skipped even when the source is present on disk. This is the most common "my
tools don't show up in `debug:mcp`" cause.

### 1. Make Composer aware of the package

Published package:

```bash
composer require swag/mcp-dev-tools
```

Local / monorepo development (source under `custom/bundles/`) — register a
Composer **path repository** first so `InstalledVersions` reports it as installed:

```bash
# from the Shopware project root
composer config repositories.swag-mcp-dev-tools path custom/bundles/SwagMcpDevTools
composer require swag/mcp-dev-tools:@dev
```

This symlinks the bundle into `vendor/`, adds it to
`vendor/composer/installed.json`, and flips the `config/bundles.php` guard to
`true`.

### 2. Register the bundle

Shopware's default `config/bundles.php` already contains the guarded line; add it
if yours does not:

```php
if (InstalledVersions::isInstalled('swag/mcp-dev-tools')) {
    $bundles[Swag\McpDevTools\SwagMcpDevToolsBundle::class] = ['all' => true];
}
```

### 3. Clear the cache (match your `APP_ENV`!)

The MCP server runs in the web process, typically `APP_ENV=prod`. Clearing the
`dev` cache will not change what the prod MCP server sees:

```bash
bin/console cache:clear --env=prod
bin/console cache:clear   # dev, if you also run the dev kernel
```

### 4. Verify and connect

```bash
bin/console debug:mcp | grep swag-dev-tools
bin/console debug:container --tag=shopware.mcp.prompt
```

Then enable the `swag-dev-tools-*` tools for your MCP integration
(Settings → Integrations → Edit MCP Tools) and reconnect the MCP client — tool
lists are fixed at connection time, so a reconnect is required to pick them up.

## Diagnostics: logs & notifications

Read-only introspection of a running instance's Monolog log files and background
operations.

| Capability | Description |
|------------|-------------|
| `swag-dev-tools-log-stream` | Tool — read recent entries from a Monolog log **file** on disk (defaults to `var/log/{env}.log`). Filter by minimum level and ISO-8601 since timestamp. |
| `swag-dev-tools-log-search` | Tool — search a Monolog log **file** for entries matching a substring. Optionally narrow by minimum level and file name. |
| `swag-dev-tools-notifications` | Tool — poll for background operation notifications (indexer completions, import/export results). Supports one-shot polling and a blocking `wait=true` mode that streams SSE progress updates until a notification arrives. |
| `swag-dev-tools-context` | Prompt — disambiguates Monolog files, the `log_entry` DAL table, business events, and background operation notifications. Pull this when "logs" or "notifications" is ambiguous. |

### Which surface do you want?

"Logs" and "notifications" mean several different things in Shopware. Pick the
right one:

| I want to… | Use | What it is |
|---|---|---|
| See runtime errors, stack traces, PHP warnings, deprecations, HTTP 500 details | `swag-dev-tools-log-stream` / `-log-search` | Monolog files on disk (`var/log/*.log`) — the full runtime stream |
| Know when indexing or an import/export finished | `swag-dev-tools-notifications` | Shopware notification entity — same data as the Admin bell icon |
| See the Admin UI's structured log viewer entries | `shopware-entity-search` on `log_entry` | DAL entity; typically business-event logs + notification writes. **Not** a full mirror of the Monolog stream. |
| Count or aggregate log entries | `shopware-entity-aggregate` on `log_entry` | Same DAL entity, aggregation path |
| See which business events exist (not runtime occurrences) | read resource `shopware://business-events` | Catalog of dispatchable events for Flow Builder |

If an LLM is using these tools against a fresh session and the question is ambiguous, pulling the `swag-dev-tools-context` prompt first gives it the same table above as a system-level instruction.

### Examples

**Triage remote errors**
- "What broke in the last hour on staging?" — `swag-dev-tools-log-stream` with `level: "ERROR"`, `since: "2026-04-22T10:00:00+00:00"`
- "Are there any critical errors right now?" — `swag-dev-tools-log-stream` with `level: "CRITICAL"`

**Pivot from an error report to context**
- "Find the stack trace for 'LineItemNotFoundException'" — `swag-dev-tools-log-search` with `query: "LineItemNotFoundException"`
- "Show log lines mentioning correlation id abc-123" — `swag-dev-tools-log-search` with `query: "abc-123"`
- "What happened around the failed order creation?" — `swag-dev-tools-log-search` with `query: "order"`, `level: "ERROR"`

**Inspect a specific log file**
- "Read entries from prod-2026-04-22.log" — `swag-dev-tools-log-stream` with `file: "prod-2026-04-22.log"`
- "Search dev.log for deprecation warnings" — `swag-dev-tools-log-search` with `query: "deprecated"`, `file: "dev.log"`

**Monitor background operations**
- "Run `dal:refresh:index` and tell me when it's done" — trigger the CLI command, then call `swag-dev-tools-notifications` with `wait: true`; SSE progress updates keep the connection alive until the indexer finishes
- "Did the product import complete?" — `swag-dev-tools-notifications` (one-shot check)
- "Check for new notifications every minute" — `swag-dev-tools-notifications` with `since` set to the previous `timestamp` value; pass the returned `timestamp` back on each call to get only genuinely new events

## Scaffolding: code generation

Scaffolding is exposed through a **single tool**, `swag-dev-tools-scaffold`, to keep
the MCP tool list small. It returns opinionated, Shopware-accurate instructions (with
the right DI tags, ACLs, route scopes, and registrations) for a connected coding
agent to follow — the MCP server never writes to disk. Progressive disclosure:

1. Call `swag-dev-tools-scaffold` with **no arguments** → the catalog: every scaffold
   `type`, a summary, and the argument names it accepts.
2. Call it with `type` + `options` (a JSON object keyed by those argument names) →
   the generation instructions for that artifact.

```
swag-dev-tools-scaffold()                              → { scaffolds: [{type, summary, arguments}, …] }
swag-dev-tools-scaffold(type: "create-admin-endpoint",
    options: '{"target":"SwagFoo","aclPrivileges":"product:read"}')  → { type, instructions }
```

Each scaffold resolves its target extension via `swag-dev-tools-list-extensions`
first, refuses to add plugin-only artifacts to apps, and points at the relevant core
Agent Skill so output follows current Shopware conventions.

**Available `type` values:** `create-plugin`, `create-theme`, `create-app`,
`create-storefront-controller`, `create-admin-endpoint` (scope + **`_acl`** + `_entity`),
`create-store-api-endpoint` (Abstract/Concrete/Response/Struct + decoration),
`create-entity` (Definition/Entity/Collection + migration + registration),
`create-migration`, `create-subscriber`, `create-admin-module`, `create-cms-element`,
`create-plugin-config`, `create-scheduled-task`, `create-message-handler`,
`create-console-command`, `create-rule`, `create-flow-action`, and `extend-plugin`
(extend an existing/composer-installed plugin **without editing it** — via template
override, subscriber, service decoration, entity extension, or JS/SCSS override).

### Support tools

| Tool | Description |
|------|-------------|
| `swag-dev-tools-list-extensions` | List installed plugins/apps (composer-aware, via the `plugin`/`app` DAL entities) with `rootPath`, `namespace`, and a `writable` flag. `writable: false` = `vendor/`, extend it — never edit in place. |
| `swag-dev-tools-list-skills` / `swag-dev-tools-load-skill` | Discover and read the Shopware Agent Skills shipped under `.agents/skills/` (core + extension-shipped). These are the authoritative source of truth for coding conventions; load the relevant one before generating code. |

`swag-dev-tools-scaffold` declares these as `#[McpToolDependsOn]`, so allowlisting the
scaffold tool for an integration auto-includes its helpers.

### Relationship to `ai-coding-tools`

Scaffolding complements the [`shopwareLabs/ai-coding-tools`](https://github.com/shopwareLabs/ai-coding-tools) marketplace: use those Claude Code plugins to lint, test, and research the code you generate. The `swag-dev-tools-suggest-tooling` prompt maps a request to the right plugin.

## Security & access control

- **No ACL privilege is required.** Access control is enforced by the MCP authentication layer plus the per-integration allowlist — there is no dedicated "read server logs" ACL privilege in Shopware, and reusing an entity privilege like `log_entry:read` (which covers the `log_entry` database table, not files on disk) would be semantically wrong. A dedicated privilege may be introduced if/when this bundle graduates out of experimental status.
- **Diagnostics tools are read-only.** They parse Monolog's default line format and **redact sensitive fields** (password, token, secret, api_key, Authorization headers, Bearer tokens, JWTs, Shopware `SWIA`/`SWUA` integration keys). Values longer than 300 characters are truncated.
- **Log file access is sandboxed.** Only files within `%kernel.logs_dir%` with a `.log` extension are readable; path traversal (`../`) is prevented.
- **Scaffolding never writes to disk.** `swag-dev-tools-scaffold` returns instructions only; the connected agent does any file writes with its own tools, and the skill loader is constrained to the `.agents/skills/` directories (no path traversal).

## Development

Run all checks from the bundle directory (`custom/bundles/SwagMcpDevTools`):

```bash
# All CI checks (PHPStan + CS + tests)
composer run ci

# Individual steps
composer run phpstan
composer run cs
composer run test
composer run cs-fix   # auto-fix code style
```

## Why a bundle and not a plugin?

`ai-coding-tools` runs on the developer's laptop and cannot reach remote environments. A Shopware plugin requires the plugin lifecycle (install, activate, database migrations) — overkill for a read-only diagnostic wrapper. A Symfony bundle installs via `composer require` + one line in `config/bundles.php`, ships with every environment (dev, staging, prod) the same way, and is the natural shape for operator tooling that only extends MCP.

## Repository

[github.com/shopware/SwagMcpDevTools](https://github.com/shopware/SwagMcpDevTools)

## Further reading

- Official Shopware MCP Server documentation: [developer.shopware.com/docs/products/tools/mcp-server/intro.html](https://developer.shopware.com/docs/products/tools/mcp-server/intro.html#mcp-server)
- Core MCP architecture and tool authoring guide: [`src/Core/Framework/Mcp/AGENTS.md`](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Mcp/AGENTS.md)
- Agent user stories for core tools: [`src/Core/Framework/Mcp/docs/agent-user-stories.md`](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Mcp/docs/agent-user-stories.md)
