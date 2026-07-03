<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateEntityPrompt;

/**
 * @internal
 */
#[CoversClass(CreateEntityPrompt::class)]
class CreateEntityPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutTrioTagAndMigration(): void
    {
        $content = $this->assertUserMessage((new CreateEntityPrompt())('SwagFoo', 'swag_widget', 'name string'));

        static::assertStringContainsString('shopware.entity.definition', $content);
        static::assertStringContainsString('SwagWidgetDefinition', $content);
        static::assertStringContainsString('migration', $content);
        static::assertStringContainsString('EntityIdTrait', $content);
    }
}
