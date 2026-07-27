<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreatePluginExtensionPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-extend-plugin',
        title: 'Extend Existing Plugin',
        description: 'Guide extending an existing (often composer-installed) Shopware plugin WITHOUT editing its files: create your own plugin that hooks in via the correct extension point — Twig template override, event subscriber, service decoration, entity extension, or admin/storefront JS/SCSS override.',
    )]
    public function __invoke(
        string $target = '',
        string $ownPlugin = '',
        string $extensionType = '',
    ): array {
        $targetLine = $target !== '' ? "**Plugin to extend:** {$target}" : 'The plugin to extend was not named — ask the user which one.';
        $ownLine = $ownPlugin !== '' ? "**Your extending plugin:** {$ownPlugin}" : 'No extending plugin was named — ask the user (or create one).';

        $text = <<<PROMPT
Extend an existing Shopware plugin.

{$targetLine}
{$ownLine}
**Requested extension type:** {$extensionType}

## Golden rule

**Never modify files under `vendor/` or inside another plugin's directory.** Even
for a plugin in `custom/plugins`, do not hard-edit it. Put ALL of your changes in
your OWN plugin that hooks into the target through a supported extension point.
There is no Magento-style class rewrite in Shopware — a "hard overwrite" means
service decoration or a Twig block override, never a copy-paste of the original.

## Steps

1. Call `swag-dev-tools-list-extensions` and locate **{$target}** — note its
   `rootPath`, `namespace`, and `writable`. `writable: false` (composer/vendor) makes
   the golden rule non-negotiable. Use its source only as a READ reference (to find
   the service id, event class, entity, or template path to hook into).
2. If your extending plugin doesn't exist yet, scaffold it first with
   `swag-dev-tools-create-plugin`.
3. Emit files ONLY into your own plugin. Pick the extension point by type:

| Type | What to generate in YOUR plugin |
|---|---|
| `template` | `Resources/views/.../<file>.html.twig` that starts with `{% sw_extends '@<TargetBundle>/storefront/.../<file>.html.twig' %}` and overrides only the specific `{% block %}` — never copy the whole file. Respect theme inheritance. |
| `event` | A subscriber (`kernel.event_subscriber`) on the event the target dispatches. Find the event's FQCN in the target's source; subscribe and act via your own services. |
| `service` | Service **decoration** in `services.xml`: `<service id="MyDecorator" decorates="<target.service.id>">` with `<argument type="service" id="MyDecorator.inner"/>` (add `decoration-priority` if ordering matters). Implement the same interface; delegate to the injected `.inner` service and wrap behavior — don't replace it. |
| `entity` | An `EntityExtension` (`Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityExtension`) that adds fields/associations to the target's DAL entity, registered with tag `shopware.entity.extension`. Add a migration for any new DB columns. |
| `admin` | Override/extend an admin Vue component in `Resources/app/administration/src/` via `Component.override(...)` or a template block override; rebuild the Administration. |
| `storefront` | Override storefront JS plugins / SCSS in `Resources/app/storefront/src/`; register overrides and rebuild the storefront. |

4. Warn explicitly in your summary that the target was NOT modified, and that all
   changes live in your own plugin.

Study these references in your Shopware source: service decoration —
`src/Storefront/DependencyInjection/services.xml` (`CachedDomainLoader` decorates
`DomainLoader`); Twig override — any `{% sw_extends %}` under
`src/Storefront/Resources/views/storefront/`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= "\nAlso read `coding-guidelines/core/extendability.md` and `coding-guidelines/core/decorator-pattern.md` via `swag-dev-tools-load-skill`/the guidelines files — they define the supported extension points.";
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
