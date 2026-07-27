<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreatePluginPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-plugin',
        title: 'Create Plugin',
        description: 'Scaffold a new Shopware 6 plugin skeleton: composer.json, the Plugin base class, and services.xml. Use this to start a brand-new plugin in custom/plugins.',
    )]
    public function __invoke(
        string $pluginName = '',
        string $namespace = '',
        string $description = '',
    ): array {
        $pluginName = $pluginName !== '' ? $pluginName : 'SwagExample';
        $namespace = $namespace !== '' ? rtrim($namespace, '\\') : $pluginName;
        $description = $description !== '' ? $description : 'A Shopware 6 plugin';
        $namespaceJson = str_replace('\\', '\\\\', $namespace);

        $text = <<<PROMPT
Scaffold a new Shopware 6 plugin.

- **Plugin name (PascalCase):** {$pluginName}
- **PSR-4 namespace root:** {$namespace}
- **Description:** {$description}
- **Location:** `custom/plugins/{$pluginName}/`

Study the equivalent of `src/Core/Framework/Plugin.php` and the fixture
`tests/integration/Core/Framework/Plugin/_fixtures/plugins/SwagTestPlugin/` in your
Shopware source as living references.

Generate these files:

1. **`custom/plugins/{$pluginName}/composer.json`**
   - `"type": "shopware-platform-plugin"`
   - `"require": { "shopware/core": "*" }` (pin the range you target)
   - `"extra"`: `"shopware-plugin-class": "{$namespaceJson}\\\\{$pluginName}"`, plus
     `label`, `description`, `manufacturerLink`, `supportLink` (translatable maps).
   - `"autoload": { "psr-4": { "{$namespaceJson}\\\\": "src/" } }`

2. **`custom/plugins/{$pluginName}/src/{$pluginName}.php`** — the Plugin class:
   ```php
   <?php declare(strict_types=1);

   namespace {$namespace};

   use Shopware\\Core\\Framework\\Plugin;

   class {$pluginName} extends Plugin
   {
   }
   ```
   Only override lifecycle hooks (`install`, `activate`, `uninstall`, …) when the
   plugin actually needs them — do not override the `final` constructor.

3. **`custom/plugins/{$pluginName}/src/Resources/config/services.xml`** — a minimal
   Symfony DI container (empty `<services>` to start), loaded automatically by the
   Plugin base class from `Resources/config/`.

After scaffolding, remind the user to run `bin/console plugin:refresh` then
`plugin:install --activate {$pluginName}`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code', 'php-foundation']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
