<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreatePluginConfigPrompt;

/**
 * @internal
 */
#[CoversClass(CreatePluginConfigPrompt::class)]
class CreatePluginConfigPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutConfigXmlAndSystemConfigService(): void
    {
        $content = $this->assertUserMessage((new CreatePluginConfigPrompt())('SwagFoo', 'active bool'));

        static::assertStringContainsString('config.xml', $content);
        static::assertStringContainsString('input-field', $content);
        static::assertStringContainsString('SystemConfigService', $content);
    }
}
