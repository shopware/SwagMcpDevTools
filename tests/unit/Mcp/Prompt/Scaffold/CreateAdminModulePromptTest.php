<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateAdminModulePrompt;

/**
 * @internal
 */
#[CoversClass(CreateAdminModulePrompt::class)]
class CreateAdminModulePromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutModuleRegistrationAndAcl(): void
    {
        $content = $this->assertUserMessage((new CreateAdminModulePrompt())('SwagFoo', 'swag-widget', 'swag_widget'));

        static::assertStringContainsString('Module.register', $content);
        static::assertStringContainsString('addPrivilegeMappingEntry', $content);
        static::assertStringContainsString('snippet/', $content);
        static::assertStringContainsString('routes', $content);
    }
}
