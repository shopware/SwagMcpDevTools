<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateMigrationPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-migration',
        title: 'Create Migration',
        description: 'Scaffold a Shopware 6 database migration in an existing plugin: the MigrationStep subclass with the correct Migration<unixTimestamp><Name> class name, update()/updateDestructive(), placed under src/Migration/ for auto-discovery.',
    )]
    public function __invoke(
        string $target = '',
        string $description = '',
        string $sql = '',
    ): array {
        $description = $description !== '' ? $description : 'Describe the change';
        $migrationName = $this->toPascalCase($description);
        $sqlLine = $sql !== ''
            ? "Intended SQL:\n```sql\n{$sql}\n```"
            : 'Put the schema/data change SQL in `update()`.';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a migration in <rootPath>

- **Description:** {$description}
- **Location:** `<rootPath>/src/Migration/` (namespace `<Namespace>\\Migration`).
  Plugins auto-discover migrations from this directory via `getMigrationNamespace()`
  — **no services.xml entry is needed.**

Study `src/Core/Framework/Migration/MigrationStep.php` and a concrete example like
`src/Core/Migration/V6_5/Migration1655697288AppFlowEvent.php`.

Generate **one** migration class:

- **Class + file name:** `Migration<TIMESTAMP>{$migrationName}` where `<TIMESTAMP>`
  is the **current Unix timestamp** (seconds). Get it now with `date +%s` — do NOT
  use a placeholder, rounded, or reused value.
- Extend `Shopware\\Core\\Framework\\Migration\\MigrationStep`.
- `getCreationTimestamp(): int` returns that exact same timestamp.
- `update(Connection \$connection): void` — the forward change. {$sqlLine}
  Prefer `CREATE TABLE IF NOT EXISTS` and the `\$this->addColumn(...)` helper for
  idempotency. New tables get `id BINARY(16) NOT NULL PRIMARY KEY`,
  `created_at DATETIME(3) NOT NULL`, `updated_at DATETIME(3) NULL`.
- `updateDestructive(Connection \$connection): void` — only destructive drops
  (drop columns/tables) that must wait; leave empty otherwise. Do NOT write tests
  for an empty `updateDestructive()`.

Remind the user that migrations run on `plugin:update`/`plugin:install`, or
manually via `bin/console database:migrate --all <PluginName>`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter('Generate the migration test with the `test-writing` plugin.');

        return $this->userMessage($text);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $value) ?? $value)));
    }
}
