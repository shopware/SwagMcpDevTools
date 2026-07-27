<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $data = json_decode(($this->tool())(), true);

        static::assertTrue($data['success']);
        static::assertSame(3, $data['_meta']['count']);

        $byType = [];
        foreach ($data['data'] as $entry) {
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
        $data = json_decode(($this->tool())(
            'create-admin-endpoint',
            (string) json_encode(['target' => 'SwagFoo', 'aclPrivileges' => 'product:read']),
        ), true);

        static::assertTrue($data['success']);
        static::assertSame('create-admin-endpoint', $data['data']['type']);
        static::assertStringContainsString('_acl', $data['data']['instructions']);
        static::assertStringContainsString('product:read', $data['data']['instructions']);
    }

    public function testDispatchWithoutOptionsUsesDefaults(): void
    {
        $data = json_decode(($this->tool())('create-plugin'), true);

        static::assertTrue($data['success']);
        static::assertStringContainsString('SwagExample', $data['data']['instructions']);
    }

    public function testUnknownTypeReturnsError(): void
    {
        $data = json_decode(($this->tool())('does-not-exist'), true);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Unknown scaffold type', $data['error']);
    }

    public function testInvalidOptionsJsonReturnsError(): void
    {
        $data = json_decode(($this->tool())('create-plugin', 'not-json'), true);

        static::assertFalse($data['success']);
        static::assertStringContainsString('must be a JSON object', $data['error']);
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
