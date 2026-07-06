<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Swag\McpDevTools\Mcp\Tool\ListExtensionsTool;
use Swag\McpDevTools\Mcp\Tool\ListSkillsTool;
use Swag\McpDevTools\Mcp\Tool\LoadSkillTool;
use Swag\McpDevTools\Mcp\Tool\LogSearchTool;
use Swag\McpDevTools\Mcp\Tool\LogStreamTool;
use Swag\McpDevTools\Mcp\Tool\NotificationsTool;
use Swag\McpDevTools\Mcp\Tool\ScaffoldTool;

/**
 * @internal
 */
final class ToolGroupingTest extends TestCase
{
    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolGroupIsAssigned(string $toolClass, string $expectedGroup, bool $expectedVisible): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $groupAttributes = $reflection->getAttributes(McpToolGroup::class);
        static::assertCount(
            1,
            $groupAttributes,
            \sprintf('%s must have exactly one #[McpToolGroup] attribute.', $toolClass),
        );

        $group = $groupAttributes[0]->newInstance()->group;
        static::assertSame(
            $expectedGroup,
            $group,
            \sprintf('%s must belong to group "%s".', $toolClass, $expectedGroup),
        );
    }

    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolDeferralMatchesMapping(string $toolClass, string $expectedGroup, bool $expectedVisible): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $toolAttributes = $reflection->getAttributes(McpTool::class);
        static::assertCount(
            1,
            $toolAttributes,
            \sprintf('%s must have exactly one #[McpTool] attribute.', $toolClass),
        );

        $meta = $toolAttributes[0]->newInstance()->meta;

        if ($expectedVisible) {
            static::assertIsArray(
                $meta,
                \sprintf('%s must declare meta to stay visible.', $toolClass),
            );
            static::assertFalse(
                $meta['deferred'] ?? true,
                \sprintf('%s must be visible (meta[deferred] === false).', $toolClass),
            );

            return;
        }

        $deferred = $meta === null || ($meta['deferred'] ?? true) === true;
        static::assertTrue(
            $deferred,
            \sprintf('%s must be deferred by default.', $toolClass),
        );
    }

    /**
     * @return list<array{class-string, string, bool}>
     */
    public static function toolProvider(): array
    {
        return [
            'LogSearchTool visible in dev-logs' => [LogSearchTool::class, 'dev-logs', true],
            'LogStreamTool deferred in dev-logs' => [LogStreamTool::class, 'dev-logs', false],
            'ListExtensionsTool deferred in dev-extensions' => [ListExtensionsTool::class, 'dev-extensions', false],
            'NotificationsTool deferred in dev-extensions' => [NotificationsTool::class, 'dev-extensions', false],
            'ListSkillsTool deferred in dev-skills' => [ListSkillsTool::class, 'dev-skills', false],
            'LoadSkillTool deferred in dev-skills' => [LoadSkillTool::class, 'dev-skills', false],
            'ScaffoldTool deferred in dev-scaffold' => [ScaffoldTool::class, 'dev-scaffold', false],
        ];
    }
}
