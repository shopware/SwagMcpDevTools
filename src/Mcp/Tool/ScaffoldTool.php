<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\AbstractScaffoldPrompt;

/**
 * Single entry point for all code-generation scaffolds, so the MCP tool list stays
 * small (one tool instead of ~20). Progressive disclosure:
 *
 *  - Call with no `type` to get the catalog: every scaffold, its summary, and the
 *    argument names it accepts.
 *  - Call with `type` + `options` (a JSON object keyed by those argument names) to
 *    get the Shopware-accurate generation instructions the agent should follow.
 *
 * The individual scaffold builders live under Mcp/Prompt/Scaffold and are collected
 * via the `swag.dev_tools.scaffold` DI tag. Their `#[McpPrompt]` attribute is reused
 * here as catalog metadata (name → type, description → summary); they are NOT
 * registered as standalone MCP prompts.
 */
#[McpTool(
    name: 'swag-dev-tools-scaffold',
    title: 'Scaffold Shopware Code',
    description: 'Generate Shopware extension code the right way. Call with no arguments to list every available scaffold (plugin, theme, app, storefront/admin/store-api endpoints, custom entity, migration, subscriber, admin module, cms element, plugin config, scheduled task, message handler, console command, rule, flow action, and extend-plugin) plus the argument names each accepts. Then call again with type=<type> and options (a JSON object keyed by those argument names, e.g. {"target":"SwagFoo","aclPrivileges":"product:read"}) to get opinionated, convention-correct instructions to follow. Resolve the target extension first with swag-dev-tools-list-extensions; the server returns instructions only and never writes files.',
)]
#[McpToolDependsOn('swag-dev-tools-list-extensions')]
#[McpToolDependsOn('swag-dev-tools-list-skills')]
#[McpToolDependsOn('swag-dev-tools-load-skill')]
class ScaffoldTool extends McpToolResponse
{
    /**
     * @var array<string, AbstractScaffoldPrompt>
     */
    private array $scaffolds = [];

    /**
     * @param iterable<AbstractScaffoldPrompt> $scaffolds
     */
    public function __construct(iterable $scaffolds)
    {
        foreach ($scaffolds as $scaffold) {
            $this->scaffolds[$this->typeOf($scaffold)] = $scaffold;
        }

        ksort($this->scaffolds);
    }

    public function __invoke(string $type = '', string $options = ''): string
    {
        if ($type === '') {
            return $this->success($this->catalog(), [
                'count' => \count($this->scaffolds),
                'usage' => 'Call again with type=<type> and options (a JSON object keyed by the listed argument names) to get generation instructions.',
            ]);
        }

        if (!isset($this->scaffolds[$type])) {
            return $this->error(\sprintf(
                'Unknown scaffold type "%s". Available: %s',
                $type,
                implode(', ', array_keys($this->scaffolds)),
            ));
        }

        $args = [];
        if ($options !== '') {
            $decoded = json_decode($options, true);
            if (!\is_array($decoded)) {
                return $this->error('options must be a JSON object, e.g. {"target":"SwagFoo","path":"/store-api/example"}');
            }
            $args = $decoded;
        }

        return $this->success([
            'type' => $type,
            'instructions' => $this->build($this->scaffolds[$type], $args),
        ]);
    }

    /**
     * @return list<array{type: string, summary: string, arguments: list<string>}>
     */
    private function catalog(): array
    {
        $catalog = [];
        foreach ($this->scaffolds as $type => $scaffold) {
            $catalog[] = [
                'type' => $type,
                'summary' => $this->summaryOf($scaffold),
                'arguments' => $this->argumentsOf($scaffold),
            ];
        }

        return $catalog;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function build(AbstractScaffoldPrompt $scaffold, array $args): string
    {
        $method = new \ReflectionMethod($scaffold, '__invoke');

        $positional = [];
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (\array_key_exists($name, $args)) {
                $positional[] = (string) $args[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $positional[] = $parameter->getDefaultValue();
            } else {
                $positional[] = '';
            }
        }

        /** @var list<array{role: string, content: string}> $envelope */
        $envelope = $method->invokeArgs($scaffold, $positional);

        return $envelope[0]['content'] ?? '';
    }

    private function typeOf(AbstractScaffoldPrompt $scaffold): string
    {
        $name = $this->promptAttribute($scaffold)->name ?? '';
        $type = preg_replace('/^swag-dev-tools-/', '', $name);

        return ($type !== null && $type !== '')
            ? $type
            : (new \ReflectionClass($scaffold))->getShortName();
    }

    private function summaryOf(AbstractScaffoldPrompt $scaffold): string
    {
        return $this->promptAttribute($scaffold)->description ?? '';
    }

    /**
     * @return list<string>
     */
    private function argumentsOf(AbstractScaffoldPrompt $scaffold): array
    {
        $method = new \ReflectionMethod($scaffold, '__invoke');

        return array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );
    }

    private function promptAttribute(AbstractScaffoldPrompt $scaffold): McpPrompt
    {
        $attributes = (new \ReflectionMethod($scaffold, '__invoke'))->getAttributes(McpPrompt::class);

        if ($attributes === []) {
            throw new \LogicException(\sprintf('Scaffold "%s" is missing the #[McpPrompt] attribute on __invoke().', $scaffold::class));
        }

        return $attributes[0]->newInstance();
    }
}
