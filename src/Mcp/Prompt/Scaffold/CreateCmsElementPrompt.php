<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateCmsElementPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-cms-element',
        title: 'Create CMS Element',
        description: 'Scaffold a Shopware 6 CMS (Shopping Experiences) element in an existing plugin: the backend data resolver (shopware.cms.data_resolver) AND the admin registration (cmsService.registerCmsElement with component/config/preview) plus the storefront template.',
    )]
    public function __invoke(
        string $target = '',
        string $elementName = '',
        string $label = '',
    ): array {
        $elementName = $elementName !== '' ? $elementName : 'swag-example';
        $label = $label !== '' ? $label : 'Example element';
        $type = str_replace('-', '_', $elementName);

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a CMS element in <rootPath>

- **Element type:** `{$type}` (admin registration name: `{$elementName}`)
- **Label:** {$label}

A CMS element has TWO halves — do not forget either.

**Backend (PHP)** — study
`src/Core/Content/Cms/DataResolver/Element/TextCmsElementResolver.php`:
1. `{Name}CmsElementResolver extends AbstractCmsElementResolver`:
   - `getType(): string` → `'{$type}'`
   - `collect(CmsSlotEntity \$slot, ResolverContext \$ctx): ?CriteriaCollection` —
     declare data to fetch (return `null` if none).
   - `enrich(CmsSlotEntity \$slot, ResolverContext \$ctx, ElementDataCollection \$result): void` —
     build a Struct and `\$slot->setData(\$struct)`.
2. A `{Name}Struct extends \\Shopware\\Core\\Framework\\Struct\\Struct` for the slot data.
3. Register the resolver in the plugin's `services.xml`:
   ```xml
   <service id="<Namespace>\...\{Name}CmsElementResolver">
       <tag name="shopware.cms.data_resolver"/>
   </service>
   ```

**Admin (JS)** under `<rootPath>/src/Resources/app/administration/src/module/sw-cms/elements/{$elementName}/`:
1. `index.js`:
   ```js
   Shopware.Service('cmsService').registerCmsElement({
       name: '{$type}',
       label: '{$elementName}.label',
       component: 'sw-cms-el-{$elementName}',
       configComponent: 'sw-cms-el-config-{$elementName}',
       previewComponent: 'sw-cms-el-preview-{$elementName}',
       defaultConfig: { /* ... */ },
   });
   ```
2. `component/`, `config/`, `preview/` sub-folders (each a Vue component +
   `.html.twig`). Import this element from the plugin's admin entry.

**Storefront template** — `src/Resources/views/storefront/element/cms-element-{$type}.html.twig`
to render the slot data.

Remind the user to rebuild the Administration and the storefront.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code', 'shopware-admin-js']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
