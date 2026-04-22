<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Tool\LogSearchTool;

/**
 * @internal
 */
#[CoversClass(LogSearchTool::class)]
class LogSearchToolTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = sys_get_temp_dir() . '/mcp-log-search-' . uniqid('', true);
        mkdir($this->logsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logsDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logsDir);
    }

    public function testErrorWhenQueryMissing(): void
    {
        $data = $this->invoke();

        static::assertFalse($data['success']);
        static::assertStringContainsString('query is required', $data['error']);
    }

    public function testReturnsErrorWhenLogFileMissing(): void
    {
        $data = $this->invoke(['query' => 'error']);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Log file not found', $data['error']);
    }

    public function testFindsMatchingEntries(): void
    {
        $this->writeLog('test.log', [
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: unrelated line [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: needle in haystack [] []',
            '[2026-04-22T10:02:00.000000+00:00] shopware.ERROR: another needle [] []',
        ]);

        $data = $this->invoke(['query' => 'needle']);

        static::assertTrue($data['success']);
        static::assertCount(2, $data['data']);
    }

    public function testFiltersByLevel(): void
    {
        $this->writeLog('test.log', [
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: needle [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: needle [] []',
        ]);

        $data = $this->invoke(['query' => 'needle', 'level' => 'ERROR']);

        static::assertCount(1, $data['data']);
        static::assertSame('ERROR', $data['data'][0]['level']);
    }

    public function testReturnsRawLineWhenUnparseable(): void
    {
        $this->writeLog('test.log', [
            'malformed line containing needle but not in monolog format',
        ]);

        $data = $this->invoke(['query' => 'needle']);

        static::assertCount(1, $data['data']);
        static::assertArrayHasKey('raw', $data['data'][0]);
    }

    public function testRespectsMaxLimit(): void
    {
        $lines = [];
        for ($i = 0; $i < 100; ++$i) {
            $lines[] = "[2026-04-22T10:00:{$i}.000000+00:00] shopware.INFO: needle {$i} [] []";
        }
        $this->writeLog('test.log', $lines);

        $data = $this->invoke(['query' => 'needle', 'limit' => 500]);

        static::assertLessThanOrEqual(50, \count($data['data']));
    }

    public function testPathTraversalIsPrevented(): void
    {
        $data = $this->invoke(['query' => 'x', 'file' => '../../../etc/passwd']);

        static::assertFalse($data['success']);
    }

    /**
     * @param list<string> $lines
     */
    private function writeLog(string $name, array $lines): void
    {
        file_put_contents($this->logsDir . '/' . $name, implode("\n", $lines) . "\n");
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function invoke(array $args = []): array
    {
        $tool = new LogSearchTool($this->logsDir, 'test');
        $output = $tool(...$args);

        return json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    }
}
