<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Swag\McpDevTools\Mcp\Tool\ScaffoldTool;

/**
 * Base class for the scaffolding builders.
 *
 * These return opinionated, Shopware-accurate instruction templates that guide a
 * connected coding agent to GENERATE the files with its own editing tools — the
 * MCP server never writes to disk itself.
 *
 * Note: subclasses are NOT registered as standalone MCP prompts. They are collected
 * by {@see ScaffoldTool} via the `swag.dev_tools.scaffold`
 * DI tag and exposed through the single `swag-dev-tools-scaffold` tool. Their
 * `#[McpPrompt]` attribute is reused there as catalog metadata (name → type,
 * description → summary) rather than for prompt registration.
 *
 * The shared helpers below keep the cross-cutting guidance (target resolution,
 * plugin-only guardrail, the "respect core's shipped skills" block, and the
 * ai-coding-tools footer) DRY and consistent across every prompt.
 *
 * The message envelope matches the shape used by the existing
 * DevToolsContextPrompt: a single user message whose content is a plain string.
 */
abstract class AbstractScaffoldPrompt
{
    /**
     * Wraps prompt text in the canonical single user-message envelope.
     *
     * @return list<array{role: string, content: string}>
     */
    protected function userMessage(string $text): array
    {
        return [
            [
                'role' => 'user',
                'content' => $text,
            ],
        ];
    }

    /**
     * The "where does this go?" block. Every prompt that targets an existing
     * extension prepends this so the agent resolves the correct root + namespace
     * instead of guessing.
     *
     * @param bool $pluginOnly When true, adds the app guardrail (refuse app targets).
     */
    protected function targetResolution(string $target, bool $pluginOnly = true): string
    {
        $targetLine = $target !== ''
            ? "Requested target extension: **{$target}**."
            : 'No target extension was given.';

        $guardrail = $pluginOnly
            ? <<<'GUARD'

**Plugin-only guardrail.** This artifact is a PHP/plugin concept. If the resolved
target is an **app** (apps run outside Shopware over HTTP), do NOT generate it.
Refuse, explain why, and offer either (a) creating/using a plugin instead, or
(b) the app-appropriate equivalent (a webhook, action-button, admin module, or
permission declared in `manifest.xml`).
GUARD
            : '';

        return <<<TARGET
## Step 0 — resolve the target extension

{$targetLine}

Before writing anything, determine WHERE the files belong:

1. Call `swag-dev-tools-list-extensions` to list installed plugins/apps with their
   `rootPath`, `namespace`, `type`, and `writable` flag.
2. Pick the entry matching the target. Use its `rootPath` as the base and its
   `namespace` as the PSR-4 root (or read the extension's `composer.json`:
   `autoload.psr-4` key → base dir, `extra.shopware-plugin-class` → namespace root).
3. If the target is **empty or ambiguous** (multiple candidates), STOP and ask the
   user which extension to use — never pick silently.
4. If the resolved entry has `writable: false` (composer/`vendor` install), STOP:
   never edit it in place. Use `swag-dev-tools-extend-plugin` to hook in from your
   own custom plugin instead.
{$guardrail}
TARGET;
    }

    /**
     * The "respect core's shipped skills" footer. Points the agent at the
     * authoritative `.agents/skills/` guidance and flags the core-vs-extension
     * caveat.
     *
     * @param list<string> $skills Skill names relevant to this artifact.
     */
    protected function skillFooter(array $skills): string
    {
        $intro = $skills !== []
            ? 'Load and follow the core Agent Skill(s): '
                . implode(', ', array_map(static fn (string $s): string => "`{$s}`", $skills)) . '.'
            : 'Check whether a relevant core Agent Skill applies.';

        return <<<SKILLS

## Follow Shopware's shipped guidance (authoritative)

{$intro} Use
`swag-dev-tools-list-skills` to see what is available and `swag-dev-tools-load-skill`
to read a skill body (and the `coding-guidelines/core/*` files it references).
These are the single source of truth for architecture/style — **if a skill
conflicts with a hint above, the skill wins.**

Caveat: those skills are written for CORE contribution. Rules about BC promises,
`@internal`/`@final` public-surface policy, `RELEASE_INFO`/`UPGRADE` docs, and
adding OpenAPI JSON under `src/Core/...` are **core-only** and do not apply to an
extension (which cannot write into `src/Core` and gets its Store/Admin API schema
auto-generated). The architecture rules (hexagonal services, prefer existing
extension points, migration timestamp discipline, admin ACL/Jest conventions) DO
apply.
SKILLS;
    }

    /**
     * Footer nudging the developer toward the shopwareLabs/ai-coding-tools plugins
     * for validation/tests once the files are generated.
     */
    protected function toolingFooter(string $extra = ''): string
    {
        $extraLine = $extra !== '' ? "\n{$extra}" : '';

        return <<<TOOLING

## After generating

Validate the result. The `shopwareLabs/ai-coding-tools` marketplace has Claude Code
plugins for this: `dev-tooling` (PHPStan/ECS/PHPUnit/ESLint/…) and `test-writing`
(PHPUnit generation). Install with:
`/plugin marketplace add shopwareLabs/ai-coding-tools` then
`/plugin install dev-tooling@shopware-ai-coding-tools`.
For the full request→plugin map, use the `swag-dev-tools-suggest-tooling` prompt.{$extraLine}
TOOLING;
    }
}
