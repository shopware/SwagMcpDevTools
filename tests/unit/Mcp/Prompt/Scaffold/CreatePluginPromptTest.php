<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreatePluginPrompt;

/**
 * @internal
 */
#[CoversClass(CreatePluginPrompt::class)]
class CreatePluginPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testReturnsUserMessageWithPluginEssentials(): void
    {
        $content = $this->assertUserMessage((new CreatePluginPrompt())('SwagFoo', 'Swag\\Foo', 'desc'));

        static::assertStringContainsString('SwagFoo', $content);
        static::assertStringContainsString('shopware-platform-plugin', $content);
        static::assertStringContainsString('shopware-plugin-class', $content);
    }

    public function testWorksWithDefaults(): void
    {
        $content = $this->assertUserMessage((new CreatePluginPrompt())());

        static::assertStringContainsString('SwagExample', $content);
    }
}
