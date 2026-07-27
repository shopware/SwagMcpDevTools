<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Swag\McpDevTools\Mcp\Tool\ListSkillsTool;

/**
 * @internal
 */
#[CoversClass(ListSkillsTool::class)]
class ListSkillsToolTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/mcp-skills-' . uniqid('', true);
        mkdir($this->projectDir . '/.agents/skills/shopware-php-code', 0777, true);
        file_put_contents(
            $this->projectDir . '/.agents/skills/shopware-php-code/SKILL.md',
            "---\nname: shopware-php-code\ndescription: Apply Shopware PHP guidance.\n---\n\n# Body\n",
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

    public function testListsCoreSkills(): void
    {
        $data = json_decode(($this->tool($this->projectDir))(), true);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']);
        static::assertSame('shopware-php-code', $data['data'][0]['name']);
        static::assertSame('core', $data['data'][0]['source']);
        static::assertSame('Apply Shopware PHP guidance.', $data['data'][0]['description']);
    }

    public function testGracefulWhenNoSkillsDir(): void
    {
        $data = json_decode(($this->tool(sys_get_temp_dir() . '/mcp-nope-' . uniqid('', true)))(), true);

        static::assertTrue($data['success']);
        static::assertSame([], $data['data']);
    }

    private function tool(string $projectDir): ListSkillsTool
    {
        return new ListSkillsTool($this->emptyRepo('plugin'), $this->emptyRepo('app'), $projectDir);
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
