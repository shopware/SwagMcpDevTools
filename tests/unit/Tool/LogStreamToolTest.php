<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Tool\LogStreamTool;

/**
 * @internal
 */
#[CoversClass(LogStreamTool::class)]
class LogStreamToolTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = sys_get_temp_dir() . '/mcp-log-test-' . uniqid('', true);
        mkdir($this->logsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logsDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logsDir);
    }

    public function testReadsEntriesFromDefaultEnvLogFile(): void
    {
        $this->writeLog('test.log', [
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: first message [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.ERROR: second message [] []',
        ]);

        $data = $this->invoke();

        static::assertTrue($data['success']);
        static::assertSame('test.log', $data['_meta']['file']);
        static::assertCount(2, $data['data']);
        static::assertSame('second message', $data['data'][0]['message']);
        static::assertSame('ERROR', $data['data'][0]['level']);
    }

    public function testReturnsErrorWhenLogFileMissing(): void
    {
        $data = $this->invoke();

        static::assertFalse($data['success']);
        static::assertStringContainsString('Log file not found', $data['error']);
    }

    public function testFiltersByMinimumLevel(): void
    {
        $this->writeLog('test.log', [
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: info line [] []',
            '[2026-04-22T10:01:00.000000+00:00] shopware.WARNING: warn line [] []',
            '[2026-04-22T10:02:00.000000+00:00] shopware.ERROR: error line [] []',
        ]);

        $data = $this->invoke(['level' => 'WARNING']);

        static::assertCount(2, $data['data']);
        $levels = array_column($data['data'], 'level');
        static::assertNotContains('INFO', $levels);
    }

    public function testFiltersBySinceTimestamp(): void
    {
        $this->writeLog('test.log', [
            '[2026-04-22T09:00:00.000000+00:00] shopware.INFO: old [] []',
            '[2026-04-22T10:00:00.000000+00:00] shopware.INFO: new [] []',
        ]);

        $data = $this->invoke(['since' => '2026-04-22T09:30:00+00:00']);

        static::assertCount(1, $data['data']);
        static::assertSame('new', $data['data'][0]['message']);
    }

    public function testRejectsNonLogFiles(): void
    {
        file_put_contents($this->logsDir . '/secrets.env', 'APP_SECRET=x');

        $data = $this->invoke(['file' => 'secrets.env']);

        static::assertFalse($data['success']);
    }

    public function testPathTraversalIsPrevented(): void
    {
        $data = $this->invoke(['file' => '../../../etc/passwd']);

        static::assertFalse($data['success']);
    }

    public function testLimitCappedAt100(): void
    {
        $lines = [];
        for ($i = 0; $i < 150; ++$i) {
            $lines[] = "[2026-04-22T10:00:{$i}.000000+00:00] shopware.INFO: msg {$i} [] []";
        }
        $this->writeLog('test.log', $lines);

        $data = $this->invoke(['limit' => 500]);

        static::assertLessThanOrEqual(100, \count($data['data']));
    }

    public function testTruncatesLongMessages(): void
    {
        $longMessage = str_repeat('A', 1000);
        $this->writeLog('test.log', [
            "[2026-04-22T10:00:00.000000+00:00] shopware.ERROR: {$longMessage} [] []",
        ]);

        $data = $this->invoke();

        static::assertStringEndsWith('…', $data['data'][0]['message']);
        static::assertLessThanOrEqual(501, mb_strlen($data['data'][0]['message']));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function sensitiveKeyProvider(): iterable
    {
        yield 'password' => ['password', true];
        yield 'Password' => ['Password', true];
        yield 'userPassword' => ['userPassword', true];
        yield 'access_token' => ['access_token', true];
        yield 'accessToken' => ['accessToken', true];
        yield 'sw-access-key' => ['sw-access-key', true];
        yield 'sw-secret-access-key' => ['sw-secret-access-key', true];
        yield 'api_key' => ['api_key', true];
        yield 'apiKey' => ['apiKey', true];
        yield 'authorization' => ['authorization', true];
        yield 'bearer' => ['bearer', true];
        yield 'credentials' => ['credentials', true];
        yield 'csrf_token' => ['csrf_token', true];

        yield 'monkey (not sensitive)' => ['monkey', false];
        yield 'keyboard (not sensitive)' => ['keyboard', false];
        yield 'keywords (not sensitive)' => ['keywords', false];
        yield 'username (not sensitive)' => ['username', false];
        yield 'route (not sensitive)' => ['route', false];
        yield 'channel (not sensitive)' => ['channel', false];
    }

    #[DataProvider('sensitiveKeyProvider')]
    public function testRedactionMatchesKeyNamesWithWordBoundaries(string $key, bool $shouldRedact): void
    {
        $result = LogStreamTool::redactSensitiveFields([$key => 'some_value']);

        if ($shouldRedact) {
            static::assertSame('[REDACTED]', $result[$key]);
        } else {
            static::assertSame('some_value', $result[$key]);
        }
    }

    public function testRedactsSecretShapedValuesRegardlessOfKeyName(): void
    {
        $result = LogStreamTool::redactSensitiveFields([
            'header' => 'Bearer abc.def.ghi',
            'some_field' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIn0.abcdef',
            'integration' => 'SWIAeXlvdXJfYWNjZXNzX2tleV9oZXJlMTIzNDU=',
            'normal' => 'hello world',
        ]);

        static::assertSame('[REDACTED]', $result['header']);
        static::assertSame('[REDACTED]', $result['some_field']);
        static::assertSame('[REDACTED]', $result['integration']);
        static::assertSame('hello world', $result['normal']);
    }

    public function testRedactionRecursesIntoNestedArrays(): void
    {
        $result = LogStreamTool::redactSensitiveFields([
            'request' => [
                'headers' => ['Authorization' => 'Bearer xyz'],
                'route' => '/api/product',
            ],
        ]);

        static::assertSame('[REDACTED]', $result['request']['headers']['Authorization']);
        static::assertSame('/api/product', $result['request']['route']);
    }

    public function testParseLineReturnsNullForUnparseableInput(): void
    {
        static::assertNull(LogStreamTool::parseLine('not a log line'));
        static::assertNull(LogStreamTool::parseLine(''));
    }

    public function testParseLineExtractsFields(): void
    {
        $parsed = LogStreamTool::parseLine(
            '[2026-04-22T10:00:00.000000+00:00] shopware.WARNING: something bad {"id":42} []',
        );

        static::assertNotNull($parsed);
        static::assertSame('2026-04-22T10:00:00.000000+00:00', $parsed['datetime']);
        static::assertSame('shopware', $parsed['channel']);
        static::assertSame('WARNING', $parsed['level']);
        static::assertSame('something bad', $parsed['message']);
        static::assertSame(42, $parsed['context']['id']);
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
        $tool = new LogStreamTool($this->logsDir, 'test');
        $output = $tool(...$args);

        return json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    }
}
