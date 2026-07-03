<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateAppPrompt;

/**
 * @internal
 */
#[CoversClass(CreateAppPrompt::class)]
class CreateAppPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutManifestAndPermissions(): void
    {
        $content = $this->assertUserMessage((new CreateAppPrompt())('MyApp', 'My App', ''));

        static::assertStringContainsString('manifest.xml', $content);
        static::assertStringContainsString('<permissions>', $content);
        static::assertStringContainsString('OUTSIDE Shopware', $content);
    }

    public function testIncludesSetupWhenBackendNeeded(): void
    {
        $content = $this->assertUserMessage((new CreateAppPrompt())('MyApp', 'My App', 'true'));

        static::assertStringContainsString('<setup>', $content);
        static::assertStringContainsString('registrationUrl', $content);
    }
}
