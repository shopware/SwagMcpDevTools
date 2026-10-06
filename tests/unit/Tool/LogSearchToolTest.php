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
        $data = json_decode((new LogSearchTool())('LineItemNotFoundException'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('demo.log', $data['_meta']['file']);
        static::assertTrue($data['_meta']['demo']);
        static::assertNotEmpty($data['data']);
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
        static::assertStringContainsString('Demo log file not found', $data['error']);
    }

    public function testFindsMatchingEntries(): void
    {
        $this->writeLog([
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
        $this->writeLog([
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: needle [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: needle [] []',
        ]);

        $data = $this->invoke(['query' => 'needle', 'level' => 'ERROR']);

        static::assertCount(1, $data['data']);
        static::assertSame('ERROR', $data['data'][0]['level']);
    }

    public function testReturnsRawLineWhenUnparseable(): void
    {
        $this->writeLog([
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
        $this->writeLog($lines);

        $data = $this->invoke(['query' => 'needle', 'limit' => 500]);

        static::assertLessThanOrEqual(50, \count($data['data']));
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
     *
     * @return array<string, mixed>
     */
    private function invoke(array $args = []): array
    {
        $tool = new LogSearchTool($this->logFile);
        $output = $tool(...$args);

        return json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    }
}
