<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateAdminModulePrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-admin-module',
        title: 'Create Admin Module',
        description: 'Scaffold a Shopware 6 Administration module (Vue/JS UI) in an existing plugin: Module.register with routes + menu entry, list/detail/create pages, ACL privilege mapping, and snippets. Use for building admin CRUD UI for an entity.',
    )]
    public function __invoke(
        string $target = '',
        string $moduleName = '',
        string $entity = '',
    ): array {
        $moduleName = $moduleName !== '' ? $moduleName : 'swag-example';
        $entityLine = $entity !== ''
            ? "- **Entity to manage:** {$entity} (set `entity: '{$entity}'` in the module and use `sw-entity-listing`/`sw-data-grid` bound to the `{$entity}.repository` via `Shopware.Service('repositoryFactory')`)."
            : '- If this module manages a DAL entity, set `entity` on the module and drive the grid/detail via that repository.';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold an Administration module in <rootPath>

- **Module name (kebab):** {$moduleName}
- **Location:** `<rootPath>/src/Resources/app/administration/src/module/{$moduleName}/`
{$entityLine}

Study a small core module: `src/Administration/Resources/app/administration/src/module/sw-settings-tag/`.

Generate:

1. **`{$moduleName}/index.js`** — register the module:
   ```js
   import './acl';
   Shopware.Component.register('{$moduleName}-list', () => import('./page/{$moduleName}-list'));
   // ...detail, create
   Shopware.Module.register('{$moduleName}', {
       type: 'plugin', name: '{$moduleName}',
       title: '{$moduleName}.general.mainMenuItemGeneral', // snippet key
       color: '#9AA8B5', icon: 'regular-cog', entity: '<entity>',
       routes: {
           index:  { component: '{$moduleName}-list',   path: 'index',            meta: { privilege: '<entity>.viewer' } },
           detail: { component: '{$moduleName}-detail', path: 'detail/:id',        meta: { privilege: '<entity>.viewer', parentPath: '{$moduleName}.index' } },
           create: { component: '{$moduleName}-create', path: 'create',            meta: { privilege: '<entity>.creator', parentPath: '{$moduleName}.index' } },
       },
       navigation: [{ label: '{$moduleName}.general.mainMenuItemGeneral', path: '{$moduleName}.index', parent: 'sw-catalogue', privilege: '<entity>.viewer' }],
       // or settingsItem: {...} to place it under Settings instead
   });
   ```

2. **`page/{$moduleName}-list|detail|create/`** — each a folder with `index.js`
   (registers a Vue component), `*.html.twig`, and `*.scss`.

3. **`acl/index.js`** — register privileges (do NOT skip; routes reference these):
   ```js
   Shopware.Service('privileges').addPrivilegeMappingEntry({
       category: 'permissions', parent: null, key: '<entity>',
       roles: {
           viewer:  { privileges: ['<entity>:read'],   dependencies: [] },
           editor:  { privileges: ['<entity>:update'], dependencies: ['<entity>.viewer'] },
           creator: { privileges: ['<entity>:create'], dependencies: ['<entity>.viewer', '<entity>.editor'] },
           deleter: { privileges: ['<entity>:delete'], dependencies: ['<entity>.viewer'] },
       },
   });
   ```

4. **`snippet/en-GB.json` + `snippet/de-DE.json`** — provide the `title` and label
   keys referenced above.

5. Ensure the plugin's admin entry (`src/Resources/app/administration/src/main.js`)
   imports `./module/{$moduleName}`.

Remind the user to rebuild the Administration (`shopware-cli` or
`bin/build-administration.sh`).
PROMPT;

        $text .= $this->skillFooter(['shopware-admin-js']);
        $text .= $this->toolingFooter('Lint/Jest the admin code with the `dev-tooling` plugin.');

        return $this->userMessage($text);
    }
}
