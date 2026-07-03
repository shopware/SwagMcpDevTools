<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateStoreApiEndpointPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-store-api-endpoint',
        title: 'Create Store API Endpoint',
        description: 'Scaffold a Shopware 6 Store API route in an existing plugin following the decoration-friendly Abstract/Concrete route + Response + Struct pattern, with the store-api route scope. OpenAPI is auto-generated from the route metadata.',
    )]
    public function __invoke(
        string $target = '',
        string $routeName = '',
        string $path = '',
        string $entity = '',
    ): array {
        $routeName = $routeName !== '' ? $routeName : 'store-api.example.load';
        $path = $path !== '' ? $path : '/store-api/example';
        $entityLine = $entity !== '' ? " operating on the `{$entity}` entity" : '';

        $text = $this->targetResolution($target);
        $text .= <<<PROMPT


## Scaffold a Store API endpoint in <rootPath>{$entityLine}

- **Route name:** {$routeName} (store-api routes are `store-api.*`)
- **Path:** {$path} (must start with `/store-api/`)
- **Location:** `<rootPath>/src/Core/Content/<Domain>/SalesChannel/`

Study the canonical trio in your Shopware source:
`src/Core/Content/ContactForm/SalesChannel/` — `AbstractContactFormRoute`,
`ContactFormRoute`, `ContactFormRouteResponse`, `ContactFormRouteResponseStruct`.

Generate this decoration-friendly set (four files):

1. **`Abstract<Name>Route`** — abstract class with `abstract public function
   getDecorated(): Abstract<Name>Route;` and the business method (e.g.
   `abstract public function load(Criteria \$criteria, SalesChannelContext \$context): <Name>RouteResponse;`).

2. **`<Name>Route extends Abstract<Name>Route`** — the concrete route:
   - Class-level scope: `#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]` (`store-api`).
   - `getDecorated()` throws `new DecorationPatternException(self::class);`.
   - The action: `#[Route(path: '{$path}', name: '{$routeName}', methods: ['POST'])]`;
     the **last** parameter is always `SalesChannelContext`.

3. **`<Name>RouteResponse extends StoreApiResponse`** (`Shopware\\Core\\System\\SalesChannel\\StoreApiResponse`),
   typed `@extends StoreApiResponse<<Name>RouteResponseStruct>`, exposing `getResult()`.

4. **`<Name>RouteResponseStruct`** — the payload struct.

Register the concrete route as a service in the plugin's `services.xml` (public).

OpenAPI: the Store API schema is **auto-generated** from the `#[Route]` metadata —
Shopware uses NO `#[OA\...]` attributes. Just expose a clean route; associate a DAL
entity where relevant so the generator can infer the schema.
PROMPT;

        $text .= $this->skillFooter(['shopware-php-code']);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
