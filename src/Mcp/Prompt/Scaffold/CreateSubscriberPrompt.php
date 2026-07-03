<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateSubscriberPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-subscriber',
        title: 'Create Event Subscriber',
        description: 'Scaffold a Shopware 6 event subscriber in an existing plugin: implements EventSubscriberInterface, getSubscribedEvents(), registered with the kernel.event_subscriber tag.',
    )]
    public function __invoke(
        string $target = '',
        string $subscriberName = '',
        string $events = '',
    ): array {
        $subscriberName = $subscriberName !== '' ? $subscriberName : 'ExampleSubscriber';
        $eventsLine = $events !== ''
            ? "Subscribe to: {$events}. Locate each event's FQCN (core events live under Shopware\\Core\\...; another plugin's events live in its source — use swag-dev-tools-list-extensions to find its path)."
            : 'Identify the exact event class(es) to subscribe to and their payload getters before writing the handler.';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold an event subscriber in <rootPath>

- **Subscriber:** {$subscriberName} → `<Namespace>\\Subscriber\\{$subscriberName}`
  at `<rootPath>/src/Subscriber/{$subscriberName}.php`
- {$eventsLine}

Study `src/Storefront/Framework/Routing/NotFound/NotFoundSubscriber.php`.

Requirements:
- `class {$subscriberName} implements Symfony\\Component\\EventDispatcher\\EventSubscriberInterface`.
- `public static function getSubscribedEvents(): array` mapping each event class →
  handler method (string, or `['method', priority]`).
- Handlers stay thin: translate the event into a call to a service; inject
  collaborators via the constructor. Do not put infrastructure logic in the subscriber.
- Register in the plugin's `services.xml` with the tag:
  ```xml
  <service id="<Namespace>\Subscriber\\{$subscriberName}">
      <!-- <argument type="service" id="..."/> for each dependency -->
      <tag name="kernel.event_subscriber"/>
  </service>
  ```
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
