<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateScheduledTaskPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-scheduled-task',
        title: 'Create Scheduled Task',
        description: 'Scaffold a Shopware 6 scheduled task in an existing plugin: the ScheduledTask (name + interval, tag shopware.scheduled.task) and its ScheduledTaskHandler (#[AsMessageHandler], tag messenger.message_handler).',
    )]
    public function __invoke(
        string $target = '',
        string $taskName = '',
        string $interval = '',
    ): array {
        $taskName = $taskName !== '' ? $taskName : 'swag_example.task';
        $className = $this->toPascalCase($taskName);
        $interval = $interval !== '' ? $interval : 'self::DAILY';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a scheduled task in <rootPath>

- **Task name:** {$taskName}
- **Class base:** {$className} → `<Namespace>\\ScheduledTask\\`
- **Default interval:** {$interval} (seconds; constants MINUTELY/HOURLY/DAILY/WEEKLY)

Study `src/Core/Framework/Log/ScheduledTask/LogCleanupTask.php` and
`LogCleanupTaskHandler.php`.

Generate two classes:

1. **`{$className} extends ScheduledTask`**
   (`Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\ScheduledTask`):
   - `public static function getTaskName(): string` → `'{$taskName}'`
   - `public static function getDefaultInterval(): int` → `{$interval}`

2. **`{$className}Handler extends ScheduledTaskHandler`** with
   `#[AsMessageHandler(handles: {$className}::class)]`; implement `run(): void`
   (keep it thin — delegate to a service).

Register in the plugin's `services.xml`:
```xml
<service id="<Namespace>\ScheduledTask\\{$className}">
    <tag name="shopware.scheduled.task"/>
</service>
<service id="<Namespace>\ScheduledTask\\{$className}Handler">
    <argument type="service" id="scheduled_task.repository"/>
    <argument type="service" id="logger"/>
    <!-- + your own dependencies -->
    <tag name="messenger.message_handler"/>
</service>
```
The task registers on plugin activation; remind the user the scheduler/consumer
must run (`bin/console scheduled-task:run` / `messenger:consume`).
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
