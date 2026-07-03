<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateConsoleCommandPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-console-command',
        title: 'Create Console Command',
        description: 'Scaffold a Shopware 6 Symfony console command in an existing plugin: #[AsCommand], configure()/execute(), registered with the console.command tag, placed in src/Command/.',
    )]
    public function __invoke(
        string $target = '',
        string $commandName = '',
    ): array {
        $commandName = $commandName !== '' ? $commandName : 'swag:example:run';
        $className = $this->toPascalCase($commandName);

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a console command in <rootPath>

- **Command signature:** `{$commandName}`
- **Class:** {$className}Command → `<Namespace>\\Command\\{$className}Command`
  at `<rootPath>/src/Command/{$className}Command.php`

Study `src/Core/System/SystemConfig/Command/ConfigGet.php`.

Generate:
```php
#[AsCommand(name: '{$commandName}', description: '...')]
class {$className}Command extends Command
{
    public function __construct(/* inject services */) { parent::__construct(); }

    protected function configure(): void
    {
        // ->addArgument(...) / ->addOption(...)
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        \$io = new SymfonyStyle(\$input, \$output);
        // ... delegate to a service
        return Command::SUCCESS;
    }
}
```

Register in the plugin's `services.xml`:
```xml
<service id="<Namespace>\Command\\{$className}Command">
    <!-- <argument type="service" id="..."/> -->
    <tag name="console.command"/>
</service>
```
(With `autoconfigure="true"` the tag is added automatically since `Command` is a
known base.) Keep the command thin — it is an infrastructure adapter; put logic in
a unit-testable service.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-', ':'], ' ', $value)));
    }
}
