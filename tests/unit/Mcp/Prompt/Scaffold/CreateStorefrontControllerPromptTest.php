<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateStorefrontControllerPrompt;

/**
 * @internal
 */
#[CoversClass(CreateStorefrontControllerPrompt::class)]
class CreateStorefrontControllerPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutScopeAndRegistration(): void
    {
        $content = $this->assertUserMessage((new CreateStorefrontControllerPrompt())('SwagFoo', 'FooController', 'frontend.foo', '/foo'));

        static::assertStringContainsString('StorefrontController', $content);
        static::assertStringContainsString('StorefrontRouteScope', $content);
        static::assertStringContainsString('setContainer', $content);
        static::assertStringContainsString('public="true"', $content);
    }
}
