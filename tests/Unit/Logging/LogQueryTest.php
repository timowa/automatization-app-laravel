<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\LogQuery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LogQueryTest extends TestCase
{
    public function test_groups_repeated_errors_and_marks_new_ones(): void
    {
        $query = new LogQuery;
        $result = $query->query([
            $this->record('ERROR', 'failed 15', 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12'),
            $this->record('ERROR', 'failed 16', 'vk.post', '2026-10-23T11:00:00Z', 'abcdef12'),
            $this->record('INFO', 'posted', 'vk.post', '2026-10-23T12:00:00Z', 'abcdef12'),
            $this->record('WARNING', 'other', 'vk.other', '2026-10-23T13:00:00Z', 'zzzzzzzz', 'http', 'vk'),
        ], [
            'from' => '2026-10-23T00:00:00Z',
            'to' => '2026-10-23T23:00:00Z',
            'known_fingerprints' => [],
        ]);

        $this->assertFalse($result['truncated']);
        $this->assertSame(3, $result['matched']);
        $this->assertCount(2, $result['groups']);
        $this->assertSame(2, $result['groups'][0]['count']);
        $this->assertSame('2026-10-23T10:00:00Z', $result['groups'][0]['first_seen']);
        $this->assertSame('2026-10-23T11:00:00Z', $result['groups'][0]['last_seen']);
        $this->assertTrue($result['groups'][0]['is_new']);
        $this->assertSame('RuntimeException', $result['groups'][0]['exception_class']);
        $this->assertSame(4, $result['groups'][0]['samples'][0]['context']['task_id']);
        $this->assertSame('***', $result['groups'][0]['samples'][0]['context']['password']);
        $this->assertStringNotContainsString('secret-password', (string) json_encode($result));
        $this->assertNull($result['events']);
    }

    public function test_request_filter_returns_related_events_of_every_level(): void
    {
        $query = new LogQuery;
        $result = $query->query([
            $this->record('ERROR', 'failed 15', 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12'),
            $this->record('INFO', 'posted', 'vk.post', '2026-10-23T12:00:00Z', 'abcdef12'),
            $this->record('WARNING', 'other', 'vk.other', '2026-10-23T13:00:00Z', 'zzzzzzzz', 'http', 'vk'),
        ], [
            'from' => '2026-10-23T00:00:00Z',
            'to' => '2026-10-23T23:00:00Z',
            'request_id' => 'abcdef12',
        ]);

        $this->assertCount(2, $result['events']);
        $this->assertSame(['ERROR', 'INFO'], array_column($result['events'], 'level'));
        $this->assertSame(2, $result['matched']);
    }

    public function test_limit_sets_truncation_flag(): void
    {
        $query = new LogQuery;
        $result = $query->query([
            $this->record('ERROR', 'failed 15', 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12'),
            $this->record('WARNING', 'other', 'vk.other', '2026-10-23T13:00:00Z', 'zzzzzzzz', 'http', 'vk'),
        ], [
            'from' => '2026-10-23T00:00:00Z',
            'to' => '2026-10-23T23:00:00Z',
            'limit' => 1,
        ]);

        $this->assertTrue($result['truncated']);
        $this->assertSame('limit', $result['truncation_reason']);
        $this->assertSame(1, $result['omitted_groups']);
        $this->assertCount(1, $result['groups']);
    }

    public function test_max_bytes_drops_payload_and_marks_truncation(): void
    {
        $query = new LogQuery;
        $result = $query->query([
            $this->record('ERROR', str_repeat('длинное сообщение ', 40), 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12'),
            $this->record('ERROR', str_repeat('другое сообщение ', 40), 'vk.other', '2026-10-23T11:00:00Z', 'abcdef12'),
        ], [
            'from' => '2026-10-23T00:00:00Z',
            'to' => '2026-10-23T23:00:00Z',
            'max_bytes' => 1000,
        ]);

        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encoded);
        $this->assertLessThanOrEqual(1000, strlen($encoded));
        $this->assertTrue($result['truncated']);
        $this->assertSame('max_bytes', $result['truncation_reason']);
    }

    public function test_known_fingerprint_is_not_new(): void
    {
        $record = $this->record('ERROR', 'failed 15', 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12');
        $fingerprint = LogQuery::fingerprint($record);
        $result = (new LogQuery)->query([$record], [
            'from' => '2026-10-23T00:00:00Z',
            'to' => '2026-10-23T23:00:00Z',
            'known_fingerprints' => [$fingerprint],
        ]);

        $this->assertFalse($result['groups'][0]['is_new']);
        $this->assertSame($fingerprint, $result['groups'][0]['fingerprint']);
    }

    public function test_scan_skips_legacy_lines_and_honors_scan_limit(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'log-query-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'job-2026-10-23.log';
        file_put_contents($path, implode("\n", [
            json_encode($this->record('ERROR', 'failed 15', 'vk.post', '2026-10-23T10:00:00Z', 'abcdef12'), JSON_UNESCAPED_UNICODE),
            '[2026-10-23 10:00:00] testing.ERROR: old line',
            json_encode($this->record('WARNING', 'other', 'vk.other', '2026-10-23T13:00:00Z', 'zzzzzzzz', 'http', 'vk'), JSON_UNESCAPED_UNICODE),
        ]));

        try {
            $limited = (new LogQuery)->scan($directory, [
                'from' => '2026-10-23T00:00:00Z',
                'to' => '2026-10-23T23:00:00Z',
                'max_lines' => 1,
            ]);
            $this->assertTrue($limited['truncated']);
            $this->assertSame('scan_limit', $limited['truncation_reason']);

            $full = (new LogQuery)->scan($directory, [
                'from' => '2026-10-23T00:00:00Z',
                'to' => '2026-10-23T23:00:00Z',
                'component' => 'http',
            ]);
            $this->assertSame(1, $full['skipped_lines']);
            $this->assertSame(1, $full['matched']);
            $this->assertSame('http', $full['groups'][0]['samples'][0]['component']);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_rejects_unknown_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LogQuery)->query([], ['level' => 'nope', 'window' => 'none']);
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        string $level,
        string $message,
        string $eventName,
        string $timestamp,
        string $requestId,
        string $component = 'queue',
        string $channel = 'job',
    ): array {
        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'event_name' => $eventName,
            'exception_class' => $level === 'ERROR' ? 'RuntimeException' : null,
            'request_id' => $requestId,
            'component' => $component,
            'log_channel' => $channel,
            'context' => ['task_id' => 4, 'password' => 'secret-password'],
            'stack_trace' => 'app/Jobs/CreateVkPostJob.php:10',
        ];
    }
}
