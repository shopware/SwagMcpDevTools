<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'swag-dev-tools-log-stream',
    title: 'Log Stream',
    description: 'DEMO ONLY: read recent entries from the synthetic demo log shipped inside this bundle (Monolog line format). It does NOT read the server\'s real var/log files; that was removed on purpose, because real logs can contain credentials and customer data. Filter by minimum level (DEBUG/INFO/NOTICE/WARNING/ERROR/CRITICAL/ALERT/EMERGENCY) and ISO-8601 since timestamp. Use this to demonstrate log triage over MCP. DO NOT use this for the log_entry database table; query that with shopware-entity-search on entity "log_entry" instead.',
)]
#[McpToolGroup('dev-logs')]
class LogStreamTool extends McpToolResponse
{
    public const LEVEL_MAP = [
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600,
    ];
    /**
     * The only file the log tools can read: a synthetic, versioned demo log shipped with the bundle.
     * Real runtime logs are deliberately out of reach (see README "Security & access control").
     */
    public const DEMO_LOG_FILE = __DIR__ . '/../../Resources/demo/demo.log';

    private const MAX_LIMIT = 100;

    /**
     * Whole-token sensitive field names (applied after normalizing the key to snake_case).
     */
    private const SENSITIVE_KEY_TOKENS = [
        'password', 'passwd', 'pwd',
        'token', 'secret', 'bearer',
        'credential', 'credentials',
        'authorization', 'auth',
        'api_key', 'access_key', 'secret_key', 'private_key',
        'sw_access_key', 'sw_secret_access_key',
    ];

    /**
     * Matches obvious secret-shaped values regardless of key name (JWTs, Bearer headers, Shopware integration keys).
     */
    private const SENSITIVE_VALUE_REGEX = '/^(?:Bearer\s+\S+|eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+|SW[IU]A[A-Za-z0-9+\/=_\-]{20,})$/';

    public function __construct(
        private readonly string $logFile = self::DEMO_LOG_FILE,
    ) {
    }

    public function __invoke(
        string $level = '',
        string $since = '',
        int $limit = 50,
    ): string {
        if (!is_file($this->logFile) || !is_readable($this->logFile)) {
            return $this->error('Demo log file not found. The bundle installation is incomplete.');
        }

        $effectiveLimit = min($limit, self::MAX_LIMIT);
        $minLevel = $level !== '' ? (self::LEVEL_MAP[strtoupper($level)] ?? null) : null;
        $sinceTs = $since !== '' ? strtotime($since) : null;

        $lines = $this->readRecentLines($this->logFile, $effectiveLimit * 5);

        $entries = [];
        foreach ($lines as $line) {
            if (\count($entries) >= $effectiveLimit) {
                break;
            }

            $parsed = self::parseLine($line);
            if ($parsed === null) {
                continue;
            }

            if ($minLevel !== null && (self::LEVEL_MAP[$parsed['level']] ?? 0) < $minLevel) {
                continue;
            }

            if ($sinceTs !== null && strtotime($parsed['datetime']) < $sinceTs) {
                break;
            }

            $entries[] = $parsed;
        }

        return $this->success($entries, [
            'file' => basename($this->logFile),
            'demo' => true,
            'count' => \count($entries),
        ]);
    }

    /**
     * Parses a single Monolog line-format entry.
     * Format: [datetime] channel.LEVEL: message {context} {extra}
     *
     * @return array{datetime:string,channel:string,level:string,message:string,context:array<mixed>}|null
     */
    public static function parseLine(string $line): ?array
    {
        if (!preg_match(
            '/^\[([^\]]+)\] (\w+)\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (.*?) (\{.*\}|\[.*\]) (\{.*\}|\[.*\])\s*$/s',
            $line,
            $m,
        )) {
            return null;
        }

        $message = $m[4];
        $context = json_decode($m[5], true) ?? [];
        $extra = json_decode($m[6], true) ?? [];

        if (\is_array($extra) && $extra !== []) {
            $context['_extra'] = $extra;
        }

        return [
            'datetime' => $m[1],
            'channel' => $m[2],
            'level' => $m[3],
            'message' => mb_strlen($message) > 500 ? mb_substr($message, 0, 500) . '…' : $message,
            'context' => self::redactSensitiveFields($context),
        ];
    }

    /**
     * Recursively redacts values whose key name or value shape hints at sensitive data.
     *
     * @param array<mixed> $fields
     *
     * @return array<mixed>
     */
    public static function redactSensitiveFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (\is_array($value)) {
                $result[$key] = self::redactSensitiveFields($value);
                continue;
            }

            if (\is_string($value) && preg_match(self::SENSITIVE_VALUE_REGEX, $value) === 1) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            $result[$key] = \is_string($value) && mb_strlen($value) > 300
                ? mb_substr($value, 0, 300) . '…'
                : $value;
        }

        return $result;
    }

    /**
     * Reads up to $max lines from the end of the file, newest-first.
     *
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

    /**
     * Normalizes a key (camelCase/kebab-case/dotted → snake_case) and checks for exact
     * sensitive-token matches at word boundaries. This avoids false positives like
     * "monkey" (matches "key"), "keyboard" (matches "key"), "keywords".
     */
    private static function isSensitiveKey(string $key): bool
    {
        $normalized = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key);
        $normalized = strtolower(str_replace(['-', '.'], '_', $normalized));

        $parts = array_values(array_filter(explode('_', $normalized), static fn (string $p) => $p !== ''));
        if ($parts === []) {
            return false;
        }

        $joined = implode('_', $parts);

        foreach (self::SENSITIVE_KEY_TOKENS as $token) {
            if (str_contains($token, '_')) {
                if ($joined === $token
                    || str_starts_with($joined, $token . '_')
                    || str_ends_with($joined, '_' . $token)
                    || str_contains($joined, '_' . $token . '_')
                ) {
                    return true;
                }
                continue;
            }

            if (\in_array($token, $parts, true)) {
                return true;
            }
        }

        return false;
    }
}
