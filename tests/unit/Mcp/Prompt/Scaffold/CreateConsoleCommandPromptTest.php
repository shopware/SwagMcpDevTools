<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateConsoleCommandPrompt;

/**
 * @internal
 */
#[CoversClass(CreateConsoleCommandPrompt::class)]
class CreateConsoleCommandPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutAsCommandAndTag(): void
    {
        $content = $this->assertUserMessage((new CreateConsoleCommandPrompt())('SwagFoo', 'swag:foo:run'));

        static::assertStringContainsString('#[AsCommand', $content);
        static::assertStringContainsString('console.command', $content);
        static::assertStringContainsString('SwagFooRunCommand', $content);
    }
}
