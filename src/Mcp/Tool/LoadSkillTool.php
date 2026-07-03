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
use Swag\McpDevTools\Mcp\Tool\Concern\LocatesSkills;

#[McpTool(
    name: 'swag-dev-tools-load-skill',
    title: 'Load Skill',
    description: 'Read the body of a Shopware Agent Skill by name (as listed by swag-dev-tools-list-skills), e.g. "shopware-php-code" or "shopware-admin-js". Returns the SKILL.md content — the authoritative Shopware coding guidance. Load the relevant skill BEFORE generating or editing code so the output follows current conventions. Note: these skills target CORE contribution; core-only rules (BC promises, @internal/@final policy, RELEASE_INFO/UPGRADE, OpenAPI JSON under src/Core) do not apply to extension development.',
)]
class LoadSkillTool extends McpToolResponse
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

    public function __invoke(string $name = ''): string
    {
        if ($name === '') {
            return $this->error('name is required (e.g. "shopware-php-code"). Use swag-dev-tools-list-skills to discover available skills.');
        }

        $body = $this->readSkill($this->projectDir, $this->extensionRoots(), $name);

        if ($body === null) {
            return $this->error(\sprintf(
                'Skill "%s" not found. It may not exist, or this install does not ship .agents/skills (e.g. a production/composer install). Use swag-dev-tools-list-skills to see what is available.',
                $name,
            ));
        }

        return $this->success(['name' => $name, 'body' => $body]);
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
