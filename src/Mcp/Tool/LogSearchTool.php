<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'swag-dev-tools-log-search',
    title: 'Log Search',
    description: 'DEMO ONLY: search the synthetic demo log shipped inside this bundle (Monolog line format) for entries matching a substring. It does NOT read the server\'s real var/log files; that was removed on purpose, because real logs can contain credentials and customer data. Optionally narrow by minimum level (DEBUG/INFO/NOTICE/WARNING/ERROR/CRITICAL/ALERT/EMERGENCY). Scans from the most recent entries backwards. Use this to demonstrate pivoting from an error to its context, e.g. "find the stack trace for LineItemNotFoundException" or "which lines mention correlation id demo-correlation-abc-123?". DO NOT use this for the log_entry database table; query that with shopware-entity-search on entity "log_entry" plus a ContainsFilter on the message field instead.',
)]
#[McpToolGroup('dev-logs')]
class LogSearchTool extends McpToolResponse
{
    private const MAX_LIMIT = 50;
    private const SCAN_LIMIT = 5000;

    public function __construct(
        private readonly string $logFile = LogStreamTool::DEMO_LOG_FILE,
    ) {
    }

    public function __invoke(
        string $query = '',
        string $level = '',
        int $limit = 20,
    ): string {
        if ($query === '') {
            return $this->error('query is required');
        }

        if (!is_file($this->logFile) || !is_readable($this->logFile)) {
            return $this->error('Demo log file not found. The bundle installation is incomplete.');
        }

        $effectiveLimit = min($limit, self::MAX_LIMIT);
        $minLevel = $level !== '' ? (LogStreamTool::LEVEL_MAP[strtoupper($level)] ?? null) : null;

        $lines = $this->readRecentLines($this->logFile, self::SCAN_LIMIT);

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
            'file' => basename($this->logFile),
            'demo' => true,
            'count' => \count($entries),
            'scanned' => \count($lines),
        ]);
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
