<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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
        $data = json_decode(($this->tool())('shopware-php-code'), true);

        static::assertTrue($data['success']);
        static::assertSame('shopware-php-code', $data['data']['name']);
        static::assertStringContainsString('The body content', $data['data']['body']);
    }

    public function testErrorWhenNameMissing(): void
    {
        $data = json_decode(($this->tool())(), true);

        static::assertFalse($data['success']);
        static::assertStringContainsString('name is required', $data['error']);
    }

    public function testErrorForUnknownSkill(): void
    {
        $data = json_decode(($this->tool())('does-not-exist'), true);

        static::assertFalse($data['success']);
        static::assertStringContainsString('not found', $data['error']);
    }

    public function testRejectsPathTraversal(): void
    {
        $data = json_decode(($this->tool())('../../../../etc/passwd'), true);

        static::assertFalse($data['success']);
    }

    private function tool(): LoadSkillTool
    {
        return new LoadSkillTool($this->emptyRepo('plugin'), $this->emptyRepo('app'), $this->projectDir);
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
}
