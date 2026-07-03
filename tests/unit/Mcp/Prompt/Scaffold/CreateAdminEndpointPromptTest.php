<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateAdminEndpointPrompt;

/**
 * @internal
 */
#[CoversClass(CreateAdminEndpointPrompt::class)]
class CreateAdminEndpointPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutAclAndScope(): void
    {
        $content = $this->assertUserMessage((new CreateAdminEndpointPrompt())('SwagFoo', 'FooController', '/api/_admin/foo', 'product', 'product:read, product:update'));

        static::assertStringContainsString('_acl', $content);
        static::assertStringContainsString('AdministrationRouteScope', $content);
        static::assertStringContainsString('\'product:read\', \'product:update\'', $content);
    }

    public function testMentionsOpenApiGeneration(): void
    {
        $content = $this->assertUserMessage((new CreateAdminEndpointPrompt())('SwagFoo'));

        static::assertStringContainsString('OpenAPI', $content);
    }
}
