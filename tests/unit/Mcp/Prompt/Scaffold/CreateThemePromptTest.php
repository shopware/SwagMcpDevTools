<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateThemePrompt;

/**
 * @internal
 */
#[CoversClass(CreateThemePrompt::class)]
class CreateThemePromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutThemeInterfaceAndThemeJson(): void
    {
        $content = $this->assertUserMessage((new CreateThemePrompt())('SwagFoo', 'My Theme', 'Me'));

        static::assertStringContainsString('ThemeInterface', $content);
        static::assertStringContainsString('theme.json', $content);
        static::assertStringContainsString('@Plugins', $content);
    }

    public function testAsksToResolveTarget(): void
    {
        $content = $this->assertUserMessage((new CreateThemePrompt())());

        static::assertStringContainsString('swag-dev-tools-list-extensions', $content);
    }
}
