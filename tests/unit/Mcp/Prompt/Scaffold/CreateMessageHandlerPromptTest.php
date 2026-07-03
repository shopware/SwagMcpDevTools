<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateMessageHandlerPrompt;

/**
 * @internal
 */
#[CoversClass(CreateMessageHandlerPrompt::class)]
class CreateMessageHandlerPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutAsyncInterfaceAndDispatch(): void
    {
        $content = $this->assertUserMessage((new CreateMessageHandlerPrompt())('SwagFoo', 'IndexWidgets'));

        static::assertStringContainsString('AsyncMessageInterface', $content);
        static::assertStringContainsString('#[AsMessageHandler]', $content);
        static::assertStringContainsString('MessageBusInterface', $content);
        static::assertStringContainsString('IndexWidgets', $content);
    }
}
