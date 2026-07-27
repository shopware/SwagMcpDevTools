<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateScheduledTaskPrompt;

/**
 * @internal
 */
#[CoversClass(CreateScheduledTaskPrompt::class)]
class CreateScheduledTaskPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutTaskAndHandlerTags(): void
    {
        $content = $this->assertUserMessage((new CreateScheduledTaskPrompt())('SwagFoo', 'swag_foo.cleanup'));

        static::assertStringContainsString('shopware.scheduled.task', $content);
        static::assertStringContainsString('messenger.message_handler', $content);
        static::assertStringContainsString('getTaskName', $content);
    }
}
