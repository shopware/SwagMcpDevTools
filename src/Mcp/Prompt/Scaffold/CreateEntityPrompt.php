<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateEntityPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-entity',
        title: 'Create Custom Entity',
        description: 'Scaffold a custom Shopware 6 DAL entity in an existing plugin: the EntityDefinition + Entity + Collection trio, the shopware.entity.definition registration, AND the CREATE TABLE migration. Optionally hands off to admin-module for CRUD UI.',
    )]
    public function __invoke(
        string $target = '',
        string $entityName = '',
        string $fields = '',
    ): array {
        $entityName = $entityName !== '' ? $entityName : 'swag_example';
        $className = $this->toPascalCase($entityName);
        $fieldsLine = $fields !== ''
            ? "Requested fields: {$fields}. Map each to the right DAL Field type."
            : 'Add the fields the domain needs, each with the right DAL Field type and flags.';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a custom DAL entity in <rootPath>

- **Entity technical name (snake_case):** {$entityName}
- **Class base:** {$className} → `<Namespace>\\Core\\Content\\{$className}\\`
- {$fieldsLine}

Study the small `tag` entity in your Shopware source:
`src/Core/System/Tag/{TagDefinition,TagEntity,TagCollection}.php` and its
registration `src/Core/System/DependencyInjection/tag.xml`.

Generate the full trio + registration + migration:

1. **`{$className}Definition extends EntityDefinition`**
   - `public const ENTITY_NAME = '{$entityName}';`
   - `getEntityName()`, `getEntityClass()` → `{$className}Entity::class`,
     `getCollectionClass()` → `{$className}Collection::class`.
   - `defineFields()`:
     ```php
     return new FieldCollection([
         (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
         (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
         // ... more fields; associations use ManyToMany/OneToMany with CascadeDelete where apt
     ]);
     ```
   Field/flag classes live under `Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\...`
   and `.../Field\\Flag\\...`.

2. **`{$className}Entity extends Entity`** — `use EntityIdTrait;` + typed properties
   with getters/setters (prefer public readonly for pure structs where it fits).

3. **`{$className}Collection extends EntityCollection`** — typed
   `@extends EntityCollection<{$className}Entity>`, `getExpectedClass()` and
   `getApiAlias()`.

4. **Register only the Definition** in the plugin's `services.xml`:
   ```xml
   <service id="<Namespace>\Core\Content\\{$className}\\{$className}Definition">
       <tag name="shopware.entity.definition"/>
   </service>
   ```
   The `{$entityName}.repository` is created automatically from the tag.

5. **A migration** that creates the `{$entityName}` table (BINARY(16) `id` PK,
   `created_at`/`updated_at`). Follow the `swag-dev-tools-create-migration` guidance
   for the class name/timestamp rules — do NOT hand-pick a rounded timestamp.

If the user wants an Admin UI to manage this entity, follow
`swag-dev-tools-create-admin-module` next (entity: `{$entityName}`).
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter('Generate the migration test with the `test-writing` plugin.');

        return $this->userMessage($text);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
