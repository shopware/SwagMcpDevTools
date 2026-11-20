<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Swag\McpDevTools\Mcp\Tool\LogSearchTool;

/**
 * @internal
 */
#[CoversClass(LogSearchTool::class)]
class LogSearchToolTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/mcp-log-search-' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
    }

    public function testSearchesBundledDemoLogByDefault(): void
    {
        $result = (new LogSearchTool())('LineItemNotFoundException');

        static::assertFalse($result->isError());
        static::assertSame('demo.log', $result->meta['file']);
        static::assertTrue($result->meta['demo']);
        static::assertIsArray($result->data);
        static::assertNotSame([], $result->data);
    }

    public function testErrorWhenQueryMissing(): void
    {
        $result = $this->invoke();

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('query is required', $result->error->message);
    }

    public function testReturnsErrorWhenLogFileMissing(): void
    {
        $result = $this->invoke(['query' => 'error']);

        static::assertTrue($result->isError());
        static::assertNotNull($result->error);
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertStringContainsString('Demo log file not found', $result->error->message);
    }

    public function testFindsMatchingEntries(): void
    {
        $this->writeLog([
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: unrelated line [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: needle in haystack [] []',
            '[2026-04-22T10:02:00.000000+00:00] shopware.ERROR: another needle [] []',
        ]);

        $result = $this->invoke(['query' => 'needle']);

        static::assertFalse($result->isError());
        static::assertCount(2, $result->data);
    }

    public function testFiltersByLevel(): void
    {
        $this->writeLog([
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: needle [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: needle [] []',
        ]);

        $result = $this->invoke(['query' => 'needle', 'level' => 'ERROR']);

        static::assertCount(1, $result->data);
        static::assertSame('ERROR', $result->data[0]['level']);
    }

    public function testReturnsRawLineWhenUnparseable(): void
    {
        $this->writeLog([
            'malformed line containing needle but not in monolog format',
        ]);

        $result = $this->invoke(['query' => 'needle']);

        static::assertCount(1, $result->data);
        static::assertArrayHasKey('raw', $result->data[0]);
    }

    public function testRespectsMaxLimit(): void
    {
        $lines = [];
        for ($i = 0; $i < 100; ++$i) {
            $lines[] = "[2026-04-22T10:00:{$i}.000000+00:00] shopware.INFO: needle {$i} [] []";
        }
        $this->writeLog($lines);

        $result = $this->invoke(['query' => 'needle', 'limit' => 500]);

        static::assertLessThanOrEqual(50, \count($result->data));
    }

    public function testCallerCannotChooseTheLogFile(): void
    {
        $parameters = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            (new \ReflectionMethod(LogSearchTool::class, '__invoke'))->getParameters(),
        );

        static::assertSame(['query', 'level', 'limit'], $parameters);
    }

    /**
     * @param list<string> $lines
     */
    private function writeLog(array $lines): void
    {
        file_put_contents($this->logFile, implode("\n", $lines) . "\n");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function invoke(array $args = []): McpToolResult
    {
        $tool = new LogSearchTool($this->logFile);

        return $tool(...$args);
    }
}
