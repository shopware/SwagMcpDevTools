<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateFlowActionPrompt;

/**
 * @internal
 */
#[CoversClass(CreateFlowActionPrompt::class)]
class CreateFlowActionPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutFlowActionContractAndTag(): void
    {
        $content = $this->assertUserMessage((new CreateFlowActionPrompt())('SwagFoo', 'action.swag.notify'));

        static::assertStringContainsString('FlowAction', $content);
        static::assertStringContainsString('handleFlow', $content);
        static::assertStringContainsString('flow.action', $content);
        static::assertStringContainsString('key="action.swag.notify"', $content);
    }
}
