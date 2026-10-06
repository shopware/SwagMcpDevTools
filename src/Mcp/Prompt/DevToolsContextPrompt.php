<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

#[McpPrompt(
    name: 'swag-dev-tools-context',
    title: 'Dev Tools Context',
    description: 'Disambiguates the four developer data surfaces in Shopware: the demo Monolog log shipped with this bundle, the log_entry DAL table, business events, and background operation notifications (indexer/import completions). Pull this when the user asks about "logs" or "notifications" and it is not obvious which surface they mean.',
)]
class DevToolsContextPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    public function __invoke(): array
    {
        return [
            [
                'role' => 'user',
                'content' => <<<'PROMPT'
"Logs" in Shopware can mean three different things. Pick the right surface before you answer.

## 1. Demo Monolog log — this bundle (DEMO ONLY)

**Tools:** `swag-dev-tools-log-stream`, `swag-dev-tools-log-search`
**Source:** a synthetic demo log shipped inside this bundle. These tools do NOT read the server's real `var/log/*.log` files, and no parameter changes that.
**Contains:** example entries in Monolog line format: errors with a stack trace, deprecations, messenger retries, slow queries, scheduled tasks

**Use when the user asks:**
- To demonstrate or try out log triage over MCP
- "Show me the stack trace in the demo log" / "Search the demo log for 'LineItemNotFoundException'"

**Do not** present results from these tools as the state of the real server. If the user wants the server's real logs, tell them those are not exposed over MCP and must be read on the host.

## 2. `log_entry` database table — core entity tools

**Tool:** `shopware-entity-search` with `entity: "log_entry"`
**Source:** MySQL `log_entry` table (DAL entity, populated by Monolog's Doctrine handler for specific channels)
**Contains:** structured Shopware log entries surfaced in the Admin UI — typically business-event logs, notification writes, some error summaries. NOT a full mirror of the Monolog stream.

**Use when the user asks:**
- "Show me what's in the Admin log viewer"
- "Find structured log entries for channel X"
- "Count log entries by level in the last week" (use `shopware-entity-aggregate`)
- Anything that references the Admin UI's log view

## 3. Business events / flows — business-events resource

**Resource:** `shopware://business-events`
**Contains:** the catalog of dispatched business events (flows can hook into these), NOT runtime occurrences.

**Use when the user asks:**
- "Which events fire when an order is placed?"
- "What business events are available?"

## 4. Background operation notifications — this bundle

**Tool:** `swag-dev-tools-notifications`
**Source:** Shopware `notification` entity (same data as the Admin bell icon)
**Contains:** completion signals from long-running background processes: entity indexer runs, import/export jobs.

**Use when the user asks:**
- "Tell me when the indexing is done"
- "Wait for the import to finish"
- "Are there any new notifications?" / "Did the reindex complete?"
- After triggering `bin/console dal:refresh:index` or an import job

**Polling pattern:** Pass the `timestamp` value from a previous response back as `since` to get only new notifications. This avoids re-reading events you have already seen.

**Wait-until pattern:** Set `wait=true` to block until a notification arrives (up to `timeout` seconds). Progress updates stream via SSE so the connection stays alive. Example: trigger `dal:refresh:index`, then call with `wait=true` — you will be notified the moment the indexer finishes.

## How to disambiguate

If the user says **"logs"** with no other context, they usually mean the server's real logs. Those are not available over MCP. Say so, and offer the `log_entry` table or the demo log tools instead. Use the demo log tools only when the user is fine with example data.

If the user says **"the log_entry table"** or **"the Admin log view"**, use `shopware-entity-search` on `log_entry` — do NOT open a file.

If the user wants to **count** or **aggregate** log lines, use `shopware-entity-aggregate` on `log_entry` — the file-based tools stream raw text and are not suited for aggregation.

If the user asks about **"notifications"**, **"when will it be done"**, or **"did the indexer finish"**, use `swag-dev-tools-notifications` — do NOT use the log tools.

## Discovering the dev tools (toolsets and search)

The tools in this bundle are deferred and do NOT appear in the default `tools/list`. Reach them in one of two ways:

- Call `shopware-tool-search` with a natural-language query (e.g. "stream logs", "list installed plugins", "scaffold a plugin") to find and inspect matching tools, including deferred ones, then call the tool by name.
- Or call `shopware-toolset-enable` with a toolset name to add its group to `tools/list` for the session. Use `shopware-toolsets-list` to see what is available.

Dev toolsets:
- `dev-logs`: log search, log stream
- `dev-extensions`: list extensions, notifications
- `dev-skills`: list skills, load skill
- `dev-scaffold`: scaffold
PROMPT,
            ],
        ];
    }
}
