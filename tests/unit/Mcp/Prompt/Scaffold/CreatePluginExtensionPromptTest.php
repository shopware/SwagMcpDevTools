<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreatePluginExtensionPrompt;

/**
 * @internal
 */
#[CoversClass(CreatePluginExtensionPrompt::class)]
class CreatePluginExtensionPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testEnforcesNeverEditVendorAndListsExtensionPoints(): void
    {
        $content = $this->assertUserMessage((new CreatePluginExtensionPrompt())('AcmePlugin', 'SwagFoo', 'service'));

        static::assertStringContainsString('Never modify files under `vendor/`', $content);
        static::assertStringContainsString('sw_extends', $content);
        static::assertStringContainsString('decorates', $content);
        static::assertStringContainsString('EntityExtension', $content);
    }
}
