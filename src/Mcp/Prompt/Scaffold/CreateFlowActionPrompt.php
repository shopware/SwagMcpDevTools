<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateFlowActionPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-flow-action',
        title: 'Create Flow Action',
        description: 'Scaffold a Shopware 6 Flow Builder action in an existing plugin: a FlowAction (getName/requirements/handleFlow) registered with the flow.action tag (priority + key).',
    )]
    public function __invoke(
        string $target = '',
        string $actionName = '',
    ): array {
        $actionName = $actionName !== '' ? $actionName : 'action.swag.example';
        $className = $this->toPascalCase($actionName) . 'Action';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a Flow Builder action in <rootPath>

- **Action key:** {$actionName}
- **Class:** {$className} → `<Namespace>\\Core\\Content\\Flow\\Dispatching\\Action\\{$className}`

Study `src/Core/Content/Flow/Dispatching/Action/AddOrderTagAction.php`.

Generate:
```php
class {$className} extends FlowAction // Shopware\Core\Content\Flow\Dispatching\Action\FlowAction
{
    public function __construct(/* inject repositories/services */) {}

    public static function getName(): string { return '{$actionName}'; }

    public function requirements(): array
    {
        // *Aware interfaces the action needs, e.g. OrderAware::class
        return [];
    }

    public function handleFlow(StorableFlow \$flow): void
    {
        // guard: if (!\$flow->hasData(OrderAware::ORDER_ID)) return;
        // use \$flow->getContext(), \$flow->getConfig(), \$flow->getData(...)
    }
}
```
Optionally implement `DelayableAction` (can be delayed) or `TransactionalAction`.

Register with the tag (key must equal `getName()`):
```xml
<service id="<Namespace>\...\\{$className}">
    <!-- <argument type="service" id="order.repository"/> -->
    <tag name="flow.action" priority="1000" key="{$actionName}"/>
</service>
```
The action also needs an admin config component to be configurable in the Flow
Builder UI; register it via the flow action admin services if the user wants UI.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-', '.'], ' ', $value)));
    }
}
