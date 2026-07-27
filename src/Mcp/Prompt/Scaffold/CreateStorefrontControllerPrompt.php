<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateStorefrontControllerPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-storefront-controller',
        title: 'Create Storefront Controller',
        description: 'Scaffold a Shopware 6 storefront (frontend) controller in an existing plugin: correct route scope, frontend.* route name, StorefrontController base, and services.xml registration with public=true + setContainer.',
    )]
    public function __invoke(
        string $target = '',
        string $controllerName = '',
        string $routeName = '',
        string $path = '',
    ): array {
        $controllerName = $controllerName !== '' ? $controllerName : 'ExampleController';
        $routeName = $routeName !== '' ? $routeName : 'frontend.example.index';
        $path = $path !== '' ? $path : '/example';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a storefront controller in <rootPath>

- **Controller:** {$controllerName} → `<Namespace>\\Storefront\\Controller\\{$controllerName}`
  at `<rootPath>/src/Storefront/Controller/{$controllerName}.php`
- **Route name:** {$routeName} (storefront routes are conventionally `frontend.*`)
- **Path:** {$path}

Study the equivalent of `src/Storefront/Controller/MaintenanceController.php` and
its registration in `src/Storefront/DependencyInjection/controller.xml`.

Requirements (do not skip):
- Extend `Shopware\\Storefront\\Controller\\StorefrontController`.
- Set the route scope to **storefront** at the CLASS level:
  `#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]`.
- On the action, `#[Route(path: '{$path}', name: '{$routeName}', methods: ['GET'])]`.
- The last argument of the action is the `SalesChannelContext`; return a `Response`
  (use `\$this->renderStorefront('@Storefront/...', [...])` for a page).
- **Register in the plugin's `src/Resources/config/services.xml`** with
  `public="true"` AND a `setContainer` call, or `renderStorefront()` will fail:
  ```xml
  <service id="<Namespace>\Storefront\Controller\\{$controllerName}" public="true">
      <call method="setContainer">
          <argument type="service" id="service_container"/>
      </call>
  </service>
  ```
- If the controller renders a template, also create the Twig template under
  `src/Resources/views/storefront/`.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
