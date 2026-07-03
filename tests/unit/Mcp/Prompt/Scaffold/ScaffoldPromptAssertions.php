<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Assert;

/**
 * Shared assertions for scaffolding prompt tests: every prompt must return a
 * single user-role message whose content is a non-empty string.
 */
trait ScaffoldPromptAssertions
{
    /**
     * @param list<array{role: string, content: string}> $result
     */
    private function assertUserMessage(array $result): string
    {
        Assert::assertCount(1, $result);
        Assert::assertSame('user', $result[0]['role']);
        Assert::assertIsString($result[0]['content']);
        Assert::assertNotSame('', $result[0]['content']);

        return $result[0]['content'];
    }
}
