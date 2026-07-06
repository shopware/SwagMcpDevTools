<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'swag-dev-tools-log-search',
    title: 'Log Search',
    description: 'Search a Monolog application log FILE on disk (default: var/log/{env}.log) for entries matching a substring. Optionally narrow by minimum level (DEBUG/INFO/NOTICE/WARNING/ERROR/CRITICAL/ALERT/EMERGENCY) and file name. Scans from the most recent entries backwards. Use for "find the stack trace for exception X", "which log lines mention correlation-id Y?", "did we log anything about payment webhook failures?". DO NOT use this for the log_entry database table — query that with shopware-entity-search on entity "log_entry" plus a ContainsFilter on the message field instead.',
    meta: ['deferred' => false],
)]
#[McpToolGroup('dev-logs')]
class LogSearchTool extends McpToolResponse
{
    private const MAX_LIMIT = 50;
    private const SCAN_LIMIT = 5000;

    public function __construct(
        private readonly string $logsDir,
        private readonly string $environment,
    ) {
    }

    public function __invoke(
        string $query = '',
        string $file = '',
        string $level = '',
        int $limit = 20,
    ): string {
        if ($query === '') {
            return $this->error('query is required');
        }

        $logFile = $this->resolveFile($file);
        if ($logFile === null) {
            return $this->error(\sprintf(
                'Log file not found. Available files: %s',
                implode(', ', $this->listLogFiles()),
            ));
        }

        $effectiveLimit = min($limit, self::MAX_LIMIT);
        $minLevel = $level !== '' ? (LogStreamTool::LEVEL_MAP[strtoupper($level)] ?? null) : null;

        $lines = $this->readRecentLines($logFile, self::SCAN_LIMIT);

        $entries = [];
        foreach ($lines as $line) {
            if (\count($entries) >= $effectiveLimit) {
                break;
            }

            if (!str_contains($line, $query)) {
                continue;
            }

            $parsed = LogStreamTool::parseLine($line);
            if ($parsed === null) {
                $entries[] = ['raw' => mb_strlen($line) > 500 ? mb_substr($line, 0, 500) . '…' : $line];
                continue;
            }

            if ($minLevel !== null && (LogStreamTool::LEVEL_MAP[$parsed['level']] ?? 0) < $minLevel) {
                continue;
            }

            $entries[] = $parsed;
        }

        return $this->success($entries, [
            'file' => basename($logFile),
            'count' => \count($entries),
            'scanned' => \count($lines),
        ]);
    }

    private function resolveFile(string $file): ?string
    {
        $name = $file !== '' ? basename($file) : $this->environment . '.log';

        if (!str_ends_with($name, '.log')) {
            return null;
        }

        $path = $this->logsDir . \DIRECTORY_SEPARATOR . $name;

        return (is_file($path) && is_readable($path)) ? $path : null;
    }

    /**
     * @return list<string>
     */
    private function listLogFiles(): array
    {
        $files = glob($this->logsDir . '/*.log') ?: [];

        return array_map('basename', $files);
    }

    /**
     * @return list<string>
     */
    private function readRecentLines(string $path, int $max): array
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return [];
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $readSize = min($size, $max * 512 + 4096);
        fseek($handle, $size - $readSize);
        $content = fread($handle, max(1, $readSize));
        fclose($handle);

        if ($content === false) {
            return [];
        }

        $lines = array_values(array_filter(explode("\n", $content)));

        if ($readSize < $size) {
            array_shift($lines);
        }

        return array_reverse(\array_slice($lines, -$max));
    }
}
