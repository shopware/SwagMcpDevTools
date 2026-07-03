<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateThemePrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-theme',
        title: 'Create Theme',
        description: 'Scaffold a Shopware 6 storefront theme inside an existing plugin: the ThemeInterface marker on the bundle class, theme.json (styles/scripts/config fields), and the SCSS/JS layout.',
    )]
    public function __invoke(
        string $target = '',
        string $themeName = '',
        string $author = '',
    ): array {
        $themeName = $themeName !== '' ? $themeName : 'My Theme';
        $author = $author !== '' ? $author : 'Your Company';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a storefront theme in <rootPath>

- **Theme name:** {$themeName}
- **Author:** {$author}

Study the equivalent of `src/Storefront/Resources/theme.json` and
`src/Storefront/Framework/ThemeInterface.php` in your Shopware source.

Generate:

1. **Bundle class** — make the plugin's bundle class implement
   `Shopware\\Storefront\\Framework\\ThemeInterface` (marker interface, no methods):
   ```php
   use Shopware\\Storefront\\Framework\\ThemeInterface;

   class <Bundle> extends Plugin implements ThemeInterface {}
   ```

2. **`<rootPath>/src/Resources/theme.json`**
   - Required: `name`, `author`, `style[]`, `script[]`.
   - Include the `@Plugins` sentinel in `style[]`/`script[]` so plugin
     styles/scripts are still injected.
   - `style`: point at `app/storefront/src/scss/base.scss`;
     `script`: `app/storefront/dist/storefront/js/<theme>/<theme>.js`.
   - Optional `config.fields` for the theme customizer (e.g. a
     `sw-color-brand-primary` color field with `type`/`value`/`editable`/`block`/`order`).

3. **SCSS/JS sources** under `<rootPath>/src/Resources/app/storefront/src/`
   (`scss/base.scss`, `scss/overrides.scss`, `main.js`).

Remind the user to run `bin/console theme:refresh` and assign the theme to a sales
channel, then build with the storefront build (`shopware-cli`/`bin/build-storefront.sh`).
PROMPT;

        $text .= $this->skillFooter([]);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
