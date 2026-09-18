<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Swag\McpDevTools\Mcp\Tool\ListExtensionsTool;

/**
 * @internal
 */
#[CoversClass(ListExtensionsTool::class)]
class ListExtensionsToolTest extends TestCase
{
    public function testMapsPluginsAndFlagsVendorAsNotWritable(): void
    {
        $custom = $this->plugin('CustomPlugin', 'Custom\\Plugin\\CustomPlugin', 'custom/plugins/CustomPlugin');
        $vendor = $this->plugin('VendorPlugin', 'Vendor\\Plugin\\VendorPlugin', 'vendor/store.shopware.com/vendorplugin');

        $tool = new ListExtensionsTool(
            $this->pluginRepo(new PluginCollection([$custom, $vendor])),
            $this->appRepo(new AppCollection([])),
            '/var/www/shop',
            $this->contextProvider(['plugin:read', 'app:read']),
        );

        $data = json_decode($tool(), true);

        static::assertTrue($data['success']);
        static::assertCount(2, $data['data']);

        $byName = [];
        foreach ($data['data'] as $row) {
            $byName[$row['name']] = $row;
        }

        static::assertTrue($byName['CustomPlugin']['writable']);
        static::assertSame('/var/www/shop/custom/plugins/CustomPlugin', $byName['CustomPlugin']['rootPath']);
        static::assertSame('Custom\\Plugin', $byName['CustomPlugin']['namespace']);

        static::assertFalse($byName['VendorPlugin']['writable']);
    }

    public function testFilterByAppType(): void
    {
        $app = new AppEntity();
        $app->setId('0123456789abcdef0123456789abcdef');
        $app->setName('MyApp');
        $app->setPath('custom/apps/MyApp');
        $app->setActive(true);
        $app->setVersion('1.0.0');

        $tool = new ListExtensionsTool(
            $this->pluginRepo(new PluginCollection([])),
            $this->appRepo(new AppCollection([$app])),
            '/var/www/shop',
            $this->contextProvider(['plugin:read', 'app:read']),
        );

        $data = json_decode($tool('app'), true);

        static::assertCount(1, $data['data']);
        static::assertSame('app', $data['data'][0]['type']);
        static::assertTrue($data['data'][0]['writable']);
    }

    public function testDeniesListingWithoutPluginReadPrivilege(): void
    {
        $tool = new ListExtensionsTool(
            $this->pluginRepo(new PluginCollection([])),
            $this->appRepo(new AppCollection([])),
            '/var/www/shop',
            $this->contextProvider([]),
        );

        $data = json_decode($tool(), true);

        static::assertFalse($data['success']);
        static::assertSame('Missing privilege: plugin:read', $data['error']);
    }

    public function testDeniesAppListingWithoutAppReadPrivilege(): void
    {
        $tool = new ListExtensionsTool(
            $this->pluginRepo(new PluginCollection([])),
            $this->appRepo(new AppCollection([])),
            '/var/www/shop',
            $this->contextProvider(['plugin:read']),
        );

        $data = json_decode($tool('app'), true);

        static::assertFalse($data['success']);
        static::assertSame('Missing privilege: app:read', $data['error']);
    }

    /**
     * @param list<string> $privileges
     */
    private function contextProvider(array $privileges): McpContextProvider
    {
        $source = new AdminApiSource(null, 'integration-id');
        $source->setIsAdmin(false);
        $source->setPermissions($privileges);

        $provider = $this->createMock(McpContextProvider::class);
        $provider->method('getContext')->willReturn(new Context($source));

        return $provider;
    }

    private function plugin(string $name, string $baseClass, string $path): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(md5($name));
        $plugin->setName($name);
        $plugin->setBaseClass($baseClass); // @phpstan-ignore argument.type (synthetic FQCN for the test double)
        $plugin->setPath($path);
        $plugin->setActive(true);
        $plugin->setManagedByComposer(!str_starts_with($path, 'custom/'));
        $plugin->setVersion('1.0.0');
        $plugin->setLabel($name);

        return $plugin;
    }

    private function pluginRepo(PluginCollection $collection): EntityRepository
    {
        return $this->repoReturning($collection);
    }

    private function appRepo(AppCollection $collection): EntityRepository
    {
        return $this->repoReturning($collection);
    }

    private function repoReturning(PluginCollection|AppCollection $collection): EntityRepository
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        return $repo;
    }
}
