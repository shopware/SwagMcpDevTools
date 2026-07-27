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
    public function testToolGroupIsAssigned(string $toolClass, string $expectedGroup): void
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
     * Core derives the advertised (non-deferred) surface from the `discovery`
     * tool group, so every tool in this bundle is deferred and none may
     * resurrect the retired per-tool meta[deferred] flag.
     *
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolIsDeferred(string $toolClass, string $expectedGroup): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $toolAttributes = $reflection->getAttributes(McpTool::class);
        static::assertCount(
            1,
            $toolAttributes,
            \sprintf('%s must have exactly one #[McpTool] attribute.', $toolClass),
        );

        $meta = $toolAttributes[0]->newInstance()->meta;

        static::assertArrayNotHasKey(
            'deferred',
            $meta ?? [],
            \sprintf(
                '%s must not use the retired meta[deferred] flag; deferral follows the "%s" toolset group.',
                $toolClass,
                $expectedGroup,
            ),
        );
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function toolProvider(): array
    {
        return [
            'LogSearchTool in dev-logs' => [LogSearchTool::class, 'dev-logs'],
            'LogStreamTool in dev-logs' => [LogStreamTool::class, 'dev-logs'],
            'ListExtensionsTool in dev-extensions' => [ListExtensionsTool::class, 'dev-extensions'],
            'NotificationsTool in dev-extensions' => [NotificationsTool::class, 'dev-extensions'],
            'ListSkillsTool in dev-skills' => [ListSkillsTool::class, 'dev-skills'],
            'LoadSkillTool in dev-skills' => [LoadSkillTool::class, 'dev-skills'],
            'ScaffoldTool in dev-scaffold' => [ScaffoldTool::class, 'dev-scaffold'],
        ];
    }
}
