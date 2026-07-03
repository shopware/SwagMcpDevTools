<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateRulePrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-rule',
        title: 'Create Rule',
        description: 'Scaffold a Shopware 6 rule-builder condition in an existing plugin: the Rule subclass (RULE_NAME, match, getConstraints) tagged shopware.rule.definition, plus the required admin condition component so it appears in the rule builder UI.',
    )]
    public function __invoke(
        string $target = '',
        string $ruleName = '',
    ): array {
        $ruleName = $ruleName !== '' ? $ruleName : 'swagExampleRule';
        $className = ucfirst($ruleName) . 'Rule';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a rule condition in <rootPath>

- **Rule technical name:** {$ruleName}
- **Class:** {$className} → `<Namespace>\\Rule\\{$className}`

Study `src/Core/Checkout/Cart/Rule/GoodsCountRule.php` and its registration in
`src/Core/Checkout/DependencyInjection/rule.xml`.

**Backend (PHP):**
1. `{$className} extends Shopware\\Core\\Framework\\Rule\\Rule`:
   - `final public const RULE_NAME = '{$ruleName}';`
   - `getName(): string` → `self::RULE_NAME`
   - typed config properties + constructor (e.g. `\$operator`, a value)
   - `match(RuleScope \$scope): bool` — guard the scope type, then use
     `RuleComparison`/`RuleConstraints` helpers.
   - `getConstraints(): array` — Symfony constraints per config field.
2. Register with the tag:
   ```xml
   <service id="<Namespace>\Rule\\{$className}">
       <tag name="shopware.rule.definition"/>
   </service>
   ```

**Admin (JS) — required, or the rule has no UI in the rule builder:** register a
condition component via `Shopware.Service('ruleConditionDataProviderService')`
(`.addCondition({ type: '{$ruleName}', component: 'sw-condition-{$ruleName}', label: ..., scopes: [...] })`)
and provide the `sw-condition-{$ruleName}` Vue component. Study existing conditions
under `src/Administration/.../app/administration/src/app/component/rule/condition-type/`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code', 'shopware-admin-js']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
