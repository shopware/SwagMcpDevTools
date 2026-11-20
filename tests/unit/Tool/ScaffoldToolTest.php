<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateAdminEndpointPrompt;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreatePluginExtensionPrompt;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreatePluginPrompt;
use Swag\McpDevTools\Mcp\Tool\ScaffoldTool;

/**
 * @internal
 */
#[CoversClass(ScaffoldTool::class)]
class ScaffoldToolTest extends TestCase
{
    public function testCatalogListsScaffoldTypesAndArguments(): void
    {
        $result = ($this->tool())();

        static::assertFalse($result->isError());
        static::assertSame(3, $result->meta['count']);

        $byType = [];
        foreach ($result->data as $entry) {
            $byType[$entry['type']] = $entry;
        }

        static::assertArrayHasKey('create-plugin', $byType);
        static::assertArrayHasKey('create-admin-endpoint', $byType);
        static::assertArrayHasKey('extend-plugin', $byType);

        static::assertNotSame('', $byType['create-plugin']['summary']);
        static::assertContains('aclPrivileges', $byType['create-admin-endpoint']['arguments']);
    }

    public function testDispatchReturnsInstructions(): void
    {
        $result = ($this->tool())(
            'create-admin-endpoint',
            (string) json_encode(['target' => 'SwagFoo', 'aclPrivileges' => 'product:read']),
        );

        static::assertFalse($result->isError());
        static::assertSame('create-admin-endpoint', $result->data['type']);
        static::assertStringContainsString('_acl', $result->data['instructions']);
        static::assertStringContainsString('product:read', $result->data['instructions']);
    }

    public function testDispatchWithoutOptionsUsesDefaults(): void
    {
        $result = ($this->tool())('create-plugin');

        static::assertFalse($result->isError());
        static::assertStringContainsString('SwagExample', $result->data['instructions']);
    }

    public function testUnknownTypeReturnsError(): void
    {
        $result = ($this->tool())('does-not-exist');

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('Unknown scaffold type', $result->error->message);
    }

    public function testInvalidOptionsJsonReturnsError(): void
    {
        $result = ($this->tool())('create-plugin', 'not-json');

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('must be a JSON object', $result->error->message);
    }

    private function tool(): ScaffoldTool
    {
        return new ScaffoldTool([
            new CreatePluginPrompt(),
            new CreateAdminEndpointPrompt(),
            new CreatePluginExtensionPrompt(),
        ]);
    }
}
