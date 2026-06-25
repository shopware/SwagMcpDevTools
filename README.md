# SwagMcpDevTools

> [!WARNING]
> **Experimental. May be removed at any time.** This bundle is a proof of concept and is **not** covered by any stability or backwards-compatibility guarantee. It can be discontinued, archived, or removed without notice, particularly in favor of **Shopware Copilot**, which is the official, supported direction for AI-assisted workflows. API, tool names, and parameter shapes may change without notice. Do not rely on it for production-critical processes.

Developer-oriented MCP tools for remote Shopware instance introspection. Installs as a Symfony bundle alongside the core MCP server and extends it with read-only diagnostic tools — log streaming and log search over the existing `/api/_mcp` endpoint. Fills the gap the host-side `ai-coding-tools` can't cover: environments it cannot reach (SaaS, PaaS, staging, on-prem).

## Requirements

- Shopware 6.7+ (experimental; stable target 6.8)
- `MCP_SERVER=1` in `.env`
- A valid MCP integration (credentials in your client config)
- Both tools enabled for that integration under Settings → Integrations → Edit MCP Tools

No ACL privilege is required. Access control for these tools is enforced by the MCP authentication layer plus the per-integration allowlist — there is no dedicated "read server logs" ACL privilege in Shopware, and reusing an entity privilege like `log_entry:read` (which covers the `log_entry` database table, not files on disk) would be semantically wrong. A dedicated privilege may be introduced if/when this bundle graduates out of experimental status.

## Tools and prompts

| Capability | Description |
|------------|-------------|
| `swag-dev-tools-log-stream` | Tool — read recent entries from a Monolog log **file** on disk (defaults to `var/log/{env}.log`). Filter by minimum level and ISO-8601 since timestamp. |
| `swag-dev-tools-log-search` | Tool — search a Monolog log **file** for entries matching a substring. Optionally narrow by minimum level and file name. |
| `swag-dev-tools-notifications` | Tool — poll for background operation notifications (indexer completions, import/export results). Supports one-shot polling and a blocking `wait=true` mode that streams SSE progress updates until a notification arrives. |
| `swag-dev-tools-context` | Prompt — disambiguates Monolog files, the `log_entry` DAL table, business events, and background operation notifications. Pull this when "logs" or "notifications" is ambiguous. |

The log tools are **read-only**, parse Monolog's default line format, and redact sensitive fields (password, token, secret, api_key, Authorization headers, Bearer tokens, JWTs, Shopware `SWIA`/`SWUA` integration keys). Values longer than 300 characters are truncated.

### Which surface do you want?

| I want to… | Use | What it is |
|---|---|---|
| See runtime errors, stack traces, PHP warnings, deprecations, HTTP 500 details | `swag-dev-tools-log-stream` / `-log-search` | Monolog files on disk (`var/log/*.log`) — the full runtime stream |
| Know when indexing or an import/export finished | `swag-dev-tools-notifications` | Shopware notification entity — same data as the Admin bell icon |
| See the Admin UI's structured log viewer entries | `shopware-entity-search` on `log_entry` | DAL entity; typically business-event logs + notification writes. **Not** a full mirror of the Monolog stream. |
| Count or aggregate log entries | `shopware-entity-aggregate` on `log_entry` | Same DAL entity, aggregation path |
| See which business events exist (not runtime occurrences) | read resource `shopware://business-events` | Catalog of dispatchable events for Flow Builder |

If an LLM is using these tools against a fresh session and the question is ambiguous, pulling the `swag-dev-tools-context` prompt first gives it the same table above as a system-level instruction.

## What developers can do

**Triage remote errors**
- "What broke in the last hour on staging?" — `swag-dev-tools-log-stream` with `level: "ERROR"`, `since: "2026-04-22T10:00:00+00:00"`
- "Show me recent warnings or worse from the `business_events` channel" — *(once channel filtering is added; current implementation only filters by level)*
- "Are there any critical errors right now?" — `swag-dev-tools-log-stream` with `level: "CRITICAL"`

**Pivot from an error report to context**
- "Find the stack trace for 'LineItemNotFoundException'" — `swag-dev-tools-log-search` with `query: "LineItemNotFoundException"`
- "Show log lines mentioning correlation id abc-123" — `swag-dev-tools-log-search` with `query: "abc-123"`
- "What happened around the failed order creation?" — `swag-dev-tools-log-search` with `query: "order"`, `level: "ERROR"`

**Inspect a specific log file**
- "Read entries from prod-2026-04-22.log" — `swag-dev-tools-log-stream` with `file: "prod-2026-04-22.log"`
- "Search dev.log for deprecation warnings" — `swag-dev-tools-log-search` with `query: "deprecated"`, `file: "dev.log"`

Only files within `%kernel.logs_dir%` with a `.log` extension are readable. Path traversal (`../`) is prevented.

**Monitor background operations**
- "Run `dal:refresh:index` and tell me when it's done" — trigger the CLI command, then call `swag-dev-tools-notifications` with `wait: true`; SSE progress updates keep the connection alive until the indexer finishes
- "Did the product import complete?" — `swag-dev-tools-notifications` (one-shot check)
- "Check for new notifications every minute" — `swag-dev-tools-notifications` with `since` set to the previous `timestamp` value; pass the returned `timestamp` back on each call to get only genuinely new events

## Installation

As a Symfony bundle (not a Shopware plugin):

```bash
composer require swag/mcp-dev-tools
```

Register it in `config/bundles.php`:

```php
Swag\McpDevTools\SwagMcpDevToolsBundle::class => ['all' => true],
```

Clear the cache:

```bash
bin/console cache:clear
```

Verify the tools are registered:

```bash
bin/console debug:mcp --tools | grep swag-dev-tools
```

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

[github.com/shopware/SwagMcpDevTools](https://github.com/shopware/SwagMcpDevTools) *(placeholder until the bundle is published)*

## Further reading

- Core MCP architecture and tool authoring guide: `src/Core/Framework/Mcp/AGENTS.md` in the [shopware/shopware](https://github.com/shopware/shopware) repository
- Agent user stories for core tools: `src/Core/Framework/Mcp/docs/agent-user-stories.md` in the same repo
