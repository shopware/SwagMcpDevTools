<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\SuggestToolingPrompt;

/**
 * @internal
 */
#[CoversClass(SuggestToolingPrompt::class)]
class SuggestToolingPromptTest extends TestCase
{
    public function testReturnsCapabilityMapAndInstallPattern(): void
    {
        $result = (new SuggestToolingPrompt())();

        static::assertCount(1, $result);
        static::assertSame('user', $result[0]['role']);
        $content = $result[0]['content'];

        static::assertStringContainsString('shopwareLabs/ai-coding-tools', $content);
        static::assertStringContainsString('/plugin install', $content);
        static::assertStringContainsString('dev-tooling', $content);
        static::assertStringContainsString('test-writing', $content);
    }
}
