<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Swag\McpDevTools\Mcp\Tool\LoadSkillTool;

/**
 * @internal
 */
#[CoversClass(LoadSkillTool::class)]
class LoadSkillToolTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/mcp-loadskill-' . uniqid('', true);
        mkdir($this->projectDir . '/.agents/skills/shopware-php-code', 0777, true);
        file_put_contents(
            $this->projectDir . '/.agents/skills/shopware-php-code/SKILL.md',
            "---\nname: shopware-php-code\n---\n\n# The body content\n",
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir . '/.agents/skills/shopware-php-code/SKILL.md');
        @rmdir($this->projectDir . '/.agents/skills/shopware-php-code');
        @rmdir($this->projectDir . '/.agents/skills');
        @rmdir($this->projectDir . '/.agents');
        @rmdir($this->projectDir);
    }

    public function testReturnsSkillBody(): void
    {
        $result = ($this->tool())('shopware-php-code');

        static::assertFalse($result->isError());
        static::assertSame('shopware-php-code', $result->data['name']);
        static::assertStringContainsString('The body content', $result->data['body']);
    }

    public function testErrorWhenNameMissing(): void
    {
        $result = ($this->tool())();

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('name is required', $result->error->message);
    }

    public function testErrorForUnknownSkill(): void
    {
        $result = ($this->tool())('does-not-exist');

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('not found', $result->error->message);
    }

    public function testRejectsPathTraversal(): void
    {
        $result = ($this->tool())('../../../../etc/passwd');

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
    }

    private function tool(): LoadSkillTool
    {
        return new LoadSkillTool($this->emptyRepo('plugin'), $this->emptyRepo('app'), $this->projectDir, $this->contextProvider());
    }

    private function emptyRepo(string $entity): EntityRepository
    {
        $collection = $entity === 'plugin' ? new PluginCollection([]) : new AppCollection([]);
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        return $repo;
    }

    /**
     * @param list<string> $privileges
     */
    private function contextProvider(array $privileges = ['plugin:read', 'app:read']): McpContextProvider
    {
        $source = new AdminApiSource(null, 'integration-id');
        $source->setIsAdmin(false);
        $source->setPermissions($privileges);

        $provider = $this->createMock(McpContextProvider::class);
        $provider->method('getContext')->willReturn(new Context($source));

        return $provider;
    }
}
