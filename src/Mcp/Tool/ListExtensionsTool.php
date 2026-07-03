<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;

#[McpTool(
    name: 'swag-dev-tools-list-extensions',
    title: 'List Extensions',
    description: 'List installed Shopware extensions (plugins and apps) with their absolute rootPath, PSR-4 namespace, base class, whether they are managed by Composer, and a "writable" flag. Use this FIRST when scaffolding code into an existing extension so you know where files belong and which namespace to use. writable=false means the extension lives in vendor/ (composer-managed) and must NOT be edited in place — extend it from your own custom plugin instead. Composer-installed plugins are included (unlike a plain custom/plugins folder scan).',
)]
class ListExtensionsTool extends McpToolResponse
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly EntityRepository $appRepository,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(string $type = ''): string
    {
        $context = Context::createDefaultContext();
        $extensions = [];

        if ($type === '' || $type === 'plugin') {
            /** @var PluginEntity $plugin */
            foreach ($this->pluginRepository->search(new Criteria(), $context)->getEntities() as $plugin) {
                $extensions[] = $this->mapPlugin($plugin);
            }
        }

        if ($type === '' || $type === 'app') {
            /** @var AppEntity $app */
            foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
                $extensions[] = $this->mapApp($app);
            }
        }

        return $this->success($extensions, ['count' => \count($extensions)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPlugin(PluginEntity $plugin): array
    {
        $path = $plugin->getPath();
        $baseClass = $plugin->getBaseClass();
        $namespace = str_contains($baseClass, '\\')
            ? substr($baseClass, 0, (int) strrpos($baseClass, '\\'))
            : $baseClass;

        return [
            'name' => $plugin->getName(),
            'type' => 'plugin',
            'rootPath' => $this->absolutePath($path),
            'namespace' => $namespace,
            'baseClass' => $baseClass,
            'composerName' => $plugin->getComposerName(),
            'active' => $plugin->getActive(),
            'version' => $plugin->getVersion(),
            'writable' => $this->isWritable($path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapApp(AppEntity $app): array
    {
        $path = $app->getPath();

        return [
            'name' => $app->getName(),
            'type' => 'app',
            'rootPath' => $this->absolutePath($path),
            'namespace' => null,
            'baseClass' => null,
            'composerName' => null,
            'active' => $app->isActive(),
            'version' => $app->getVersion(),
            'writable' => $this->isWritable($path),
        ];
    }

    private function absolutePath(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        if (str_starts_with($relative, '/')) {
            return $relative;
        }

        return rtrim($this->projectDir, '/') . '/' . $relative;
    }

    /**
     * An extension is safe to edit in place only when it lives under custom/;
     * anything in vendor/ is composer-managed and must be extended, not edited.
     */
    private function isWritable(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'custom/');
    }
}
