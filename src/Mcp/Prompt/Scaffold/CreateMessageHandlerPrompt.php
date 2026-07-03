<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateMessageHandlerPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-message-handler',
        title: 'Create Message + Handler',
        description: 'Scaffold a Shopware 6 async message and its handler in an existing plugin: a message implementing AsyncMessageInterface and a #[AsMessageHandler], plus how to dispatch via the message bus.',
    )]
    public function __invoke(
        string $target = '',
        string $messageName = '',
    ): array {
        $messageName = $messageName !== '' ? $this->toPascalCase($messageName) : 'ExampleMessage';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold an async message + handler in <rootPath>

- **Message:** {$messageName} → `<Namespace>\\Message\\{$messageName}`
- **Handler:** {$messageName}Handler → `<Namespace>\\Message\\{$messageName}Handler`

Study `src/Core/Framework/Adapter/Cache/Message/CleanupOldCacheFolders.php` and its
handler.

Generate:

1. **`{$messageName}`** — a plain DTO implementing
   `Shopware\\Core\\Framework\\MessageQueue\\AsyncMessageInterface` (marker; routes it
   to the async transport). Use `LowPriorityMessageInterface` instead for low-prio
   work. Put payload as public readonly constructor properties.

2. **`{$messageName}Handler`** — `#[AsMessageHandler]` `final readonly class` with
   `public function __invoke({$messageName} \$message): void`. Inject collaborators
   via the constructor; keep the handler thin.

**Registration:** if the plugin's `services.xml` uses
`<defaults autowire="true" autoconfigure="true"/>`, the `#[AsMessageHandler]`
attribute is auto-registered. Otherwise add `<tag name="messenger.message_handler"/>`
to the handler service.

**Dispatch:** inject `Symfony\\Component\\Messenger\\MessageBusInterface` and call
`\$this->bus->dispatch(new {$messageName}(...));`.

Remind the user a consumer must run: `bin/console messenger:consume async`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
