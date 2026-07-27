<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateMigrationPrompt;

/**
 * @internal
 */
#[CoversClass(CreateMigrationPrompt::class)]
class CreateMigrationPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutTimestampAndLocation(): void
    {
        $content = $this->assertUserMessage((new CreateMigrationPrompt())('SwagFoo', 'Add widget table'));

        static::assertStringContainsString('MigrationStep', $content);
        static::assertStringContainsString('getCreationTimestamp', $content);
        static::assertStringContainsString('src/Migration/', $content);
        static::assertStringContainsString('AddWidgetTable', $content);
    }
}
