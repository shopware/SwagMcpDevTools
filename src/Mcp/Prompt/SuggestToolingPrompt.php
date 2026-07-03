<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Maps common Shopware developer requests to the Claude Code plugins in the
 * shopwareLabs/ai-coding-tools marketplace, so requests that fall outside this
 * runtime MCP server's scope get pointed at the right dedicated tooling.
 */
class SuggestToolingPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-suggest-tooling',
        title: 'Suggest AI Coding Tools',
        description: 'When a developer request needs static analysis, test generation, GitHub/CI work, code research, or environment bootstrapping, this maps it to the right Claude Code plugin from the shopwareLabs/ai-coding-tools marketplace and gives the install command. Pull this when a request falls outside the runtime MCP tools (logs/notifications/scaffolding).',
    )]
    public function __invoke(): array
    {
        return [
            [
                'role' => 'user',
                'content' => <<<'PROMPT'
This runtime MCP server covers logs, notifications, and scaffolding prompts. Many
developer tasks are better handled by dedicated Claude Code plugins in the
`shopwareLabs/ai-coding-tools` marketplace. When the user's request matches a row
below, suggest installing that plugin instead of half-doing the job by hand.

**Install pattern:**
```
/plugin marketplace add shopwareLabs/ai-coding-tools
/plugin install <plugin>@shopware-ai-coding-tools
```

| If the developer wants to… | Suggest plugin |
|---|---|
| Run PHPStan / ECS / Rector / PHPUnit / ESLint / Stylelint / Prettier / Jest / TS / Vite / Webpack | `dev-tooling` |
| Generate or validate PHPUnit tests | `test-writing` |
| Work with GitHub PRs / issues / CI runs / search | `gh-tooling` |
| Understand "how does X work" / architecture / trace data flow / semantic search | `chunkhound-integration` |
| Interpret CI failure logs | `ci-failure-interpretation` |
| Write an ADR / PR description / commit message / RELEASE_INFO / UPGRADE entry | `contributor-writing` |
| Deeply analyze a specific PR or issue | `code-contribution-analysis` |
| Bootstrap a dev environment / DB / frontend builds / plugin management | `shopware-env` |

Suggest, don't nag: only recommend a plugin when it clearly fits the request, and
mention it once. If a plugin is already installed (its MCP tools/skills are
available), just use it.
PROMPT,
            ],
        ];
    }
}
