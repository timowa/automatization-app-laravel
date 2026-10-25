<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use App\Logging\ConfigureStructuredLogging;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class StructuredLoggingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('framework/testing-structured-'.bin2hex(random_bytes(4)).'.log');
        config([
            'logging.default' => 'structured_test',
            'logging.channels.structured_test' => [
                'driver' => 'single',
                'path' => $this->logPath,
                'level' => 'debug',
                'replace_placeholders' => true,
                'tap' => [ConfigureStructuredLogging::class.':structured_test'],
            ],
        ]);
        Log::forgetChannel('structured_test');
        Log::forgetChannel('stack');
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_file_channels_use_json_tap(): void
    {
        foreach (['single', 'daily', 'job', 'vk', 'vk-reposts', 'vk-sync', 'vk-images', 'check_tokens', 'stats', 'llm'] as $name) {
            $tap = config("logging.channels.{$name}.tap");
            $this->assertIsArray($tap);
            $this->assertStringContainsString(ConfigureStructuredLogging::class, $tap[0]);
            $this->assertStringContainsString(':'.$name, $tap[0]);
        }
    }

    public function test_writes_jsonl_with_nested_context_and_exception(): void
    {
        try {
            throw new RuntimeException('nested-fail');
        } catch (RuntimeException $exception) {
            Log::channel('structured_test')->error('nested-fail', [
                'event_name' => 'test.exception',
                'exception' => $exception,
                'payload' => ['ok' => true, 'items' => [1, 2]],
            ]);
        }

        $row = $this->lines()[0];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $row['timestamp']);
        $this->assertSame('ERROR', $row['level']);
        $this->assertSame('nested-fail', $row['message']);
        $this->assertSame('test.exception', $row['event_name']);
        $this->assertSame(config('app.name'), $row['service']);
        $this->assertSame('testing', $row['environment']);
        $this->assertSame('structured_test', $row['log_channel']);
        $this->assertSame(RuntimeException::class, $row['exception_class']);
        $this->assertSame('nested-fail', $row['exception_message']);
        $this->assertIsInt($row['line']);
        $this->assertIsString($row['stack_trace']);
        $this->assertStringContainsString('StructuredLoggingTest.php', $row['file']);
        $this->assertTrue($row['context']['payload']['ok']);
        $this->assertSame([1, 2], $row['context']['payload']['items']);
        $this->assertArrayNotHasKey('trace_id', $row);
        $this->assertArrayNotHasKey('exception', $row['context']);
    }

    public function test_redacts_secrets_and_database_statement(): void
    {
        Log::channel('structured_test')->warning('login failed', [
            'event_name' => 'test.secret',
            'password' => 'secret-password',
            'nested' => ['authorization' => 'Bearer abc.def-ghi_jkl'],
            'note' => 'token vk1.a.abcdefghijklmnop',
        ]);

        $previous = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'secret-password'");
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $queryException = new QueryException(
            'mysql',
            'insert into users (email, password) values (?, ?)',
            ['person@example.com', 'secret-password'],
            $previous,
        );
        Log::channel('structured_test')->error($queryException->getMessage(), [
            'event_name' => 'test.sql',
            'trace' => $queryException->getTraceAsString(),
        ]);

        $raw = (string) file_get_contents($this->logPath);
        $this->assertStringNotContainsString('secret-password', $raw);
        $this->assertStringNotContainsString('person@example.com', $raw);
        $this->assertStringNotContainsString('Bearer abc.def-ghi_jkl', $raw);
        $this->assertStringNotContainsString('vk1.a.abcdefghijklmnop', $raw);
        $this->assertStringContainsString('***', $raw);
    }

    public function test_reported_query_exception_keeps_sqlstate_without_statement(): void
    {
        $previous = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $exception = new QueryException(
            'mysql',
            'insert into users (password) values (?)',
            ['secret-password'],
            $previous,
        );

        report($exception);

        $row = $this->lines()[0];
        $raw = (string) file_get_contents($this->logPath);
        $this->assertSame('exception.reported', $row['event_name']);
        $this->assertSame('23000', $row['sqlstate']);
        $this->assertSame('1062', $row['driver_code']);
        $this->assertSame(QueryException::class, $row['exception_class']);
        $this->assertStringNotContainsString('secret-password', $raw);
        $this->assertStringNotContainsString('insert into users', $raw);
    }

    public function test_validation_exception_is_not_logged_and_duplicate_report_is_once(): void
    {
        report(ValidationException::withMessages(['email' => ['bad-email-value']]));
        $this->assertSame([], $this->lines());

        $exception = new RuntimeException('boom-once-unique');
        report($exception);
        report($exception);

        $matches = array_values(array_filter(
            $this->lines(),
            static fn (array $row): bool => ($row['message'] ?? '') === 'boom-once-unique',
        ));
        $this->assertCount(1, $matches);
    }

    public function test_request_id_is_isolated_between_requests(): void
    {
        Route::get('/_logging-probe', function () {
            Log::channel('structured_test')->info('probe', ['event_name' => 'test.http']);

            return response('ok');
        });

        $this->withHeader('X-Request-Id', 'aaaaaaa1')->get('/_logging-probe')->assertOk()->assertHeader('X-Request-Id', 'aaaaaaa1');
        $this->withHeader('X-Request-Id', 'bbbbbbb2')->get('/_logging-probe')->assertOk()->assertHeader('X-Request-Id', 'bbbbbbb2');

        $rows = $this->lines();
        $this->assertSame('aaaaaaa1', $rows[0]['request_id']);
        $this->assertSame('bbbbbbb2', $rows[1]['request_id']);
        $this->assertStringNotContainsString('aaaaaaa1', (string) json_encode($rows[1], JSON_UNESCAPED_UNICODE));
        $this->assertSame('http', $rows[0]['component']);
        $this->assertSame('GET', $rows[0]['http_method']);
        $this->assertSame('/_logging-probe', $rows[0]['route']);
        $this->assertFalse(Context::has('request_id'));
    }

    public function test_invalid_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', 'bad')->get('/up');

        $response->assertOk();
        $requestId = (string) $response->headers->get('X-Request-Id');
        $this->assertNotSame('bad', $requestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $requestId);
    }

    public function test_console_and_queue_context_are_separate(): void
    {
        $input = new ArrayInput([]);
        $output = new NullOutput;
        event(new CommandStarting('logging:probe', $input, $output));
        Log::channel('structured_test')->info('probe', ['event_name' => 'test.console']);
        event(new CommandFinished('logging:probe', $input, $output, 0));

        Context::add('request_id', 'req-fixed1');
        dispatch(function (): void {
            Log::channel('structured_test')->info('job', ['event_name' => 'test.job']);
        });

        $rows = $this->lines();
        $this->assertSame('console', $rows[0]['component']);
        $this->assertSame('logging:probe', $rows[0]['operation']);
        $this->assertArrayHasKey('operation_id', $rows[0]);
        $this->assertSame('queue', $rows[1]['component']);
        $this->assertSame('req-fixed1', $rows[1]['request_id']);
        $this->assertNotSame($rows[0]['operation_id'], $rows[1]['operation_id']);
        $this->assertSame('req-fixed1', Context::get('request_id'));
        $this->assertFalse(Context::has('component'));
    }

    public function test_log_failure_does_not_replace_the_original_exception(): void
    {
        config([
            'logging.default' => 'broken',
            'logging.channels.broken' => [
                'driver' => 'single',
                'path' => storage_path('logs'),
                'level' => 'debug',
                'tap' => [ConfigureStructuredLogging::class.':broken'],
            ],
            'logging.channels.emergency.path' => $this->logPath,
        ]);
        Log::forgetChannel('broken');

        $exception = new RuntimeException('original-failure');
        report($exception);

        $this->assertSame('original-failure', $exception->getMessage());
    }

    public function test_query_command_returns_groups(): void
    {
        $directory = storage_path('framework/testing-query-'.bin2hex(random_bytes(4)));
        mkdir($directory);
        $record = [
            'timestamp' => '2026-10-23T10:00:00Z',
            'level' => 'ERROR',
            'message' => 'failed 15',
            'event_name' => 'vk.post',
            'request_id' => 'abcdef12',
            'component' => 'queue',
            'log_channel' => 'job',
            'context' => ['task_id' => 9],
        ];
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'job-2026-10-23.log',
            json_encode($record, JSON_UNESCAPED_UNICODE)."\n",
        );

        try {
            $exit = Artisan::call('logs:query', [
                '--dir' => $directory,
                '--from' => '2026-10-23T00:00:00Z',
                '--to' => '2026-10-23T23:00:00Z',
                '--event' => 'vk.post',
                '--request' => 'abcdef12',
            ]);
            $payload = json_decode(Artisan::output(), true);
        } finally {
            @unlink($directory.DIRECTORY_SEPARATOR.'job-2026-10-23.log');
            @rmdir($directory);
        }

        $this->assertSame(0, $exit);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['matched']);
        $this->assertSame('vk.post', $payload['groups'][0]['event_name']);
        $this->assertCount(1, $payload['events']);
        $this->assertSame(9, $payload['events'][0]['context']['task_id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(): array
    {
        if (! is_file($this->logPath)) {
            return [];
        }

        $rows = [];
        foreach (file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, $line);
            $rows[] = $decoded;
        }

        return $rows;
    }
}
