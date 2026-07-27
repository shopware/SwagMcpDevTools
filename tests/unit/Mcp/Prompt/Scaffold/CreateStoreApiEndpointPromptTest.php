<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateStoreApiEndpointPrompt;

/**
 * @internal
 */
#[CoversClass(CreateStoreApiEndpointPrompt::class)]
class CreateStoreApiEndpointPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutDecorationAndScopeAndOpenApi(): void
    {
        $content = $this->assertUserMessage((new CreateStoreApiEndpointPrompt())('SwagFoo', 'store-api.foo.load', '/store-api/foo', 'product'));

        static::assertStringContainsString('DecorationPatternException', $content);
        static::assertStringContainsString('StoreApiRouteScope', $content);
        static::assertStringContainsString('StoreApiResponse', $content);
        static::assertStringContainsString('auto-generated', $content);
    }
}
