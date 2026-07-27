<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool\Concern;

/**
 * Shared logic for locating and reading Shopware Agent Skills shipped under
 * `.agents/skills/` — both in the project root (core skills) and inside installed
 * extensions (plugins/apps may ship their own).
 *
 * All reads are constrained to a resolved skills directory: the skill name is
 * validated against a strict pattern and the resolved real path must stay inside
 * the skills root, so a caller cannot traverse out with `../` or absolute paths.
 */
trait LocatesSkills
{
    /**
     * Existing `.agents/skills` directories to search, in priority order.
     *
     * @param list<array{name: string, path: string}> $extensionRoots absolute extension root paths
     *
     * @return list<array{source: string, dir: string}>
     */
    private function skillDirectories(string $projectDir, array $extensionRoots): array
    {
        $dirs = [];

        $coreDir = rtrim($projectDir, '/') . '/.agents/skills';
        if (is_dir($coreDir)) {
            $dirs[] = ['source' => 'core', 'dir' => $coreDir];
        }

        foreach ($extensionRoots as $root) {
            $dir = rtrim($root['path'], '/') . '/.agents/skills';
            if (is_dir($dir)) {
                $dirs[] = ['source' => 'extension:' . $root['name'], 'dir' => $dir];
            }
        }

        return $dirs;
    }

    /**
     * @param list<array{name: string, path: string}> $extensionRoots
     *
     * @return list<array{name: string, description: string, source: string, path: string}>
     */
    private function scanSkills(string $projectDir, array $extensionRoots): array
    {
        $skills = [];

        foreach ($this->skillDirectories($projectDir, $extensionRoots) as $entry) {
            foreach (glob($entry['dir'] . '/*/SKILL.md') ?: [] as $skillFile) {
                $body = @file_get_contents($skillFile);
                if ($body === false) {
                    continue;
                }

                $skills[] = [
                    'name' => basename(\dirname($skillFile)),
                    'description' => $this->frontmatterField($body, 'description') ?? '',
                    'source' => $entry['source'],
                    'path' => $skillFile,
                ];
            }
        }

        return $skills;
    }

    /**
     * Reads a named skill's SKILL.md, guarding against path traversal.
     *
     * @param list<array{name: string, path: string}> $extensionRoots
     */
    private function readSkill(string $projectDir, array $extensionRoots, string $name): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $name)) {
            return null;
        }

        foreach ($this->skillDirectories($projectDir, $extensionRoots) as $entry) {
            $file = $entry['dir'] . '/' . $name . '/SKILL.md';
            $real = realpath($file);
            $rootReal = realpath($entry['dir']);

            if ($real === false || $rootReal === false || !str_starts_with($real, $rootReal . '/')) {
                continue;
            }

            $content = @file_get_contents($real);
            if ($content !== false) {
                return $content;
            }
        }

        return null;
    }

    private function frontmatterField(string $body, string $field): ?string
    {
        if (!preg_match('/^---\R(.*?)\R---/s', $body, $matches)) {
            return null;
        }

        if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)$/m', $matches[1], $fieldMatch)) {
            return trim($fieldMatch[1]);
        }

        return null;
    }
}
