<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateRulePrompt;

/**
 * @internal
 */
#[CoversClass(CreateRulePrompt::class)]
class CreateRulePromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutTagAndAdminComponent(): void
    {
        $content = $this->assertUserMessage((new CreateRulePrompt())('SwagFoo', 'swagCustomerAge'));

        static::assertStringContainsString('shopware.rule.definition', $content);
        static::assertStringContainsString('RULE_NAME', $content);
        static::assertStringContainsString('getConstraints', $content);
        static::assertStringContainsString('rule builder', $content);
    }
}
