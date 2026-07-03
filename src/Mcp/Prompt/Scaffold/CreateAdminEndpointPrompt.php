<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateAdminEndpointPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-admin-endpoint',
        title: 'Create Admin API Endpoint',
        description: 'Scaffold a Shopware 6 internal Admin API controller (/api/_admin/...) in an existing plugin, with the administration route scope and — critically — ACL privileges declared via the _acl route default. Reminds you not to forget ACLs.',
    )]
    public function __invoke(
        string $target = '',
        string $controllerName = '',
        string $path = '',
        string $entity = '',
        string $aclPrivileges = '',
    ): array {
        $controllerName = $controllerName !== '' ? $controllerName : 'ExampleAdminController';
        $path = $path !== '' ? $path : '/api/_admin/example';
        $entityLine = $entity !== ''
            ? "- **Associated entity:** {$entity} → add `PlatformRequest::ATTRIBUTE_ENTITY => '{$entity}'` to the route defaults."
            : '- If the endpoint operates on a DAL entity, add `PlatformRequest::ATTRIBUTE_ENTITY => \'<entity>\'` to the route defaults.';
        $aclLine = $aclPrivileges !== ''
            ? '[\'' . implode('\', \'', array_map('trim', explode(',', $aclPrivileges))) . '\']'
            : '[\'<entity>:read\']';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold an internal Admin API endpoint in <rootPath>

- **Controller:** {$controllerName} → `<Namespace>\\Controller\\{$controllerName}`
  at `<rootPath>/src/Controller/{$controllerName}.php`
- **Path:** {$path} (internal admin endpoints use `/api/_admin/...`, route name `api.admin.*`)
{$entityLine}

Study the equivalent of `src/Administration/Controller/AdminTagController.php`.

Requirements (do not skip):
- Extend `Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController`.
- Set the scope to **administration** at the CLASS level:
  `#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]`.
- **Declare ACL privileges on the route — this is mandatory, do not omit it.** In the
  action's `#[Route(... defaults: [...])]` add the `_acl` default
  (`PlatformRequest::ATTRIBUTE_ACL`):
  `PlatformRequest::ATTRIBUTE_ACL => {$aclLine}`.
  Privileges are `entity:operation` strings (e.g. `product:read`, `order:update`).
- Action returns a `JsonResponse`; the last argument is `Context` (or a resolved
  `Criteria`). Register the controller as a service in the plugin's `services.xml`
  (autoconfigured controllers are fine; make it `public="true"` if it needs the
  container).

If the endpoint should be part of the generated Admin API OpenAPI document, expose
it as a proper `#[Route]` with the `_entity` default — Shopware generates the schema
from route + entity metadata (there are NO `#[OA\...]` attributes in Shopware).
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
