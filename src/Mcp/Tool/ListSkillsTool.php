<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Swag\McpDevTools\Mcp\Tool\Concern\LocatesSkills;

#[McpTool(
    name: 'swag-dev-tools-list-skills',
    title: 'List Skills',
    description: 'List the Shopware Agent Skills shipped in this project under .agents/skills/ (and any skills shipped by installed extensions). Returns each skill name, description, and source (core or extension). These skills are the authoritative source of truth for Shopware coding conventions — use swag-dev-tools-load-skill to read one before generating or editing code. Returns an empty list on installs that do not ship the source (e.g. production/composer installs).',
)]
#[McpToolGroup('dev-skills')]
class ListSkillsTool extends McpToolResponse
{
    use LocatesSkills;

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

    public function __invoke(): string
    {
        $skills = $this->scanSkills($this->projectDir, $this->extensionRoots());

        return $this->success($skills, ['count' => \count($skills)]);
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function extensionRoots(): array
    {
        $context = Context::createDefaultContext();
        $roots = [];

        /** @var PluginEntity $plugin */
        foreach ($this->pluginRepository->search(new Criteria(), $context)->getEntities() as $plugin) {
            $path = $plugin->getPath();
            if ($path !== null && $path !== '') {
                $roots[] = ['name' => $plugin->getName(), 'path' => $this->absolutePath($path)];
            }
        }

        /** @var AppEntity $app */
        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            $roots[] = ['name' => $app->getName(), 'path' => $this->absolutePath($app->getPath())];
        }

        return $roots;
    }

    private function absolutePath(string $relative): string
    {
        if (str_starts_with($relative, '/')) {
            return $relative;
        }

        return rtrim($this->projectDir, '/') . '/' . $relative;
    }
}
