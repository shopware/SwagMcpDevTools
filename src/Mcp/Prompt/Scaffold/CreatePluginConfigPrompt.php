<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreatePluginConfigPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-plugin-config',
        title: 'Create Plugin Configuration',
        description: 'Scaffold a Shopware 6 plugin settings screen (Resources/config/config.xml) in an existing plugin, with input fields, and show how to read values via SystemConfigService.',
    )]
    public function __invoke(
        string $target = '',
        string $fields = '',
    ): array {
        $fieldsLine = $fields !== ''
            ? "Requested fields: {$fields}. Choose the matching input-field `type` for each."
            : 'Add the settings fields the plugin needs.';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold plugin configuration in <rootPath>

- **File:** `<rootPath>/src/Resources/config/config.xml`
- {$fieldsLine}

Study the fixture
`tests/integration/Core/Framework/Plugin/_fixtures/plugins/SwagTestPlugin/src/Resources/config/config.xml`
and the schema `src/Core/System/SystemConfig/Schema/config.xsd`.

Generate `config.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/SystemConfig/Schema/config.xsd">
    <card>
        <title>Basic configuration</title>
        <title lang="de-DE">Grundeinstellungen</title>
        <input-field type="bool">
            <name>active</name>
            <label>Enable feature</label>
            <defaultValue>false</defaultValue>
        </input-field>
        <input-field type="int">
            <name>limit</name>
            <label>Limit</label>
            <defaultValue>10</defaultValue>
        </input-field>
    </card>
</config>
```
Available `type` values include `text`, `int`, `float`, `bool`, `price`,
`single-select`, `multi-select` (with `<options><option><id>/<name></option></options>`).
Each field needs a `<name>`; add `<label>` (+ `lang` variants) and `<defaultValue>`.

**Reading values** — inject `Shopware\\Core\\System\\SystemConfig\\SystemConfigService`
and read with the key `<PluginBundleName>.config.<fieldName>`, e.g.:
```php
\$this->systemConfigService->getBool('<Bundle>.config.active');
\$this->systemConfigService->getInt('<Bundle>.config.limit'); // optional 2nd arg: salesChannelId
```

No services.xml entry is needed for `config.xml` itself; it is picked up
automatically. The settings screen appears under Extensions once the plugin is active.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
