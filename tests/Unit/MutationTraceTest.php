<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MutationTraceTest extends TestCase
{
    public static function scenarios(): array
    {
        return [['disabled', null], ['wrong_site', null], ['normal', 'finished'], ['fatal', 'fatal'], ['exit', 'interrupted']];
    }

    #[DataProvider('scenarios')]
    public function testTraceIsScopedPrivateAndSurvivesAbortedWrites(string $mode, ?string $outcome): void
    {
        $log = tempnam(sys_get_temp_dir(), 'rnl-trace-');
        try {
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1',
                dirname(__DIR__) . '/fixtures/mutation-trace.php', $mode, $log],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
            stream_get_contents($pipes[2]); fclose($pipes[2]);
            $exit = proc_close($process);
            self::assertSame($mode === 'fatal' ? 255 : 0, $exit);
            preg_match_all('/RNL-MUTATION (\{[^\n]+\})/', file_get_contents($log), $matches);
            $records = array_map(static fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
            if ($outcome === null) { self::assertSame([], $records); return; }
            self::assertCount(1, array_unique(array_column($records, 'trace_id')));
            self::assertStringNotContainsString('SECRET_SENTINEL', implode('', $matches[1]));
            self::assertStringNotContainsString('@', implode('', $matches[1]));
            $last = end($records);
            self::assertSame($outcome, $last['event']);
            self::assertSame(19, $last['site_id']);
            self::assertSame(0, $last['db_connection']);
            self::assertGreaterThan(0, $last['pid']);
            self::assertGreaterThanOrEqual(0, $last['elapsed_ms']);
            if ($mode !== 'normal') {
                self::assertSame('metadata.write', $last['phase']);
                self::assertSame(123, $last['object_id']);
                self::assertTrue($last['transaction']);
                self::assertSame($mode === 'fatal' ? E_USER_ERROR : 0, $last['error_type']);
            }
        } finally { unlink($log); }
    }
}
