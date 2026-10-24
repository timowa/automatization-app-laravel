<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeZone;
use Illuminate\Database\QueryException;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use stdClass;
use Throwable;

/**
 * Одна JSON-запись на строку. Схема ближе к полям OpenTelemetry Logs, чем вывод Monolog JsonFormatter.
 */
final class StructuredJsonFormatter extends JsonFormatter
{
    private const MAX_FRAMES = 40;

    private const MAX_TRACE = 8000;

    private const MAX_LINE = 32768;

    private static bool $formatting = false;

    public function __construct(
        private readonly string $logChannel = 'app',
        private readonly string $service = 'app',
        private readonly string $environment = 'production',
        private readonly ?string $release = null,
        private readonly ?string $commitSha = null,
        private readonly SensitiveDataRedactor $redactor = new SensitiveDataRedactor,
    ) {
        parent::__construct(self::BATCH_MODE_NEWLINES, true, true, false);
        $this->maxNormalizeDepth = 6;
        $this->maxNormalizeItemCount = 40;
    }

    public function format(LogRecord $record): string
    {
        if (self::$formatting) {
            return '';
        }

        self::$formatting = true;

        try {
            return $this->encodeLine($this->present($record));
        } catch (Throwable) {
            return $this->encodeLine($this->fallback('log.format_failed'));
        } finally {
            self::$formatting = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LogRecord $record): array
    {
        $context = $record->context;
        $extra = $record->extra;
        $exception = $context['exception'] ?? $extra['exception'] ?? null;
        unset($context['exception'], $extra['exception']);

        $eventName = $this->pullString($context, 'event_name')
            ?? $this->pullString($extra, 'event_name')
            ?? 'app.log';
        if (preg_match('/^[A-Za-z0-9]+(?:[._:-][A-Za-z0-9]+){0,12}$/', $eventName) !== 1) {
            $eventName = 'app.log';
        }

        $payload = [
            'timestamp' => $record->datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'level' => $record->level->getName(),
            'message' => $this->redactor->redactString($record->message),
            'event_name' => $eventName,
            'service' => $this->service,
            'environment' => $this->environment,
            'log_channel' => $this->logChannel,
        ];

        $this->copyLabel($payload, 'request_id', $this->pullString($context, 'request_id') ?? $this->pullString($extra, 'request_id'), 128, true);
        $this->copyLabel($payload, 'operation_id', $this->pullString($context, 'operation_id') ?? $this->pullString($extra, 'operation_id'), 128, true);
        $this->copyLabel($payload, 'trace_id', $this->pullString($context, 'trace_id') ?? $this->pullString($extra, 'trace_id'), 128, true);
        $this->copyLabel($payload, 'span_id', $this->pullString($context, 'span_id') ?? $this->pullString($extra, 'span_id'), 128, true);
        $this->copyLabel($payload, 'component', $this->pullString($context, 'component') ?? $this->pullString($extra, 'component'), 160, false);
        $this->copyLabel($payload, 'operation', $this->pullString($context, 'operation') ?? $this->pullString($extra, 'operation'), 160, false);
        $this->copyLabel($payload, 'route', $this->pullString($context, 'route') ?? $this->pullString($extra, 'route'), 180, false);
        $this->copyLabel($payload, 'http_method', $this->pullString($context, 'http_method') ?? $this->pullString($extra, 'http_method'), 10, false);

        $status = $this->pull($context, 'http_status') ?? $this->pull($extra, 'http_status');
        if (is_int($status) || (is_string($status) && ctype_digit($status))) {
            $payload['http_status'] = (int) $status;
        }

        $duration = $this->pull($context, 'duration_ms') ?? $this->pull($extra, 'duration_ms');
        if (is_int($duration) || is_float($duration)) {
            $payload['duration_ms'] = $duration;
        }

        if ($this->release !== null && $this->release !== '') {
            $payload['release'] = $this->release;
        }
        if ($this->commitSha !== null && $this->commitSha !== '') {
            $payload['commit_sha'] = $this->commitSha;
        }

        if ($exception instanceof Throwable) {
            $this->applyException($payload, $exception);
            unset($context['file'], $context['line'], $context['trace'], $context['stack_trace'], $extra['file'], $extra['line'], $extra['trace'], $extra['stack_trace']);
        } else {
            $file = $this->pullString($context, 'file') ?? $this->pullString($extra, 'file');
            $line = $this->pull($context, 'line') ?? $this->pull($extra, 'line');
            if (is_string($file) && $file !== '') {
                $payload['file'] = $this->relativePath($file);
            }
            if (is_int($line) || (is_string($line) && ctype_digit($line))) {
                $payload['line'] = (int) $line;
            }
            $trace = $this->pullString($context, 'trace')
                ?? $this->pullString($context, 'stack_trace')
                ?? $this->pullString($extra, 'trace')
                ?? $this->pullString($extra, 'stack_trace');
            if ($trace !== null) {
                $payload['stack_trace'] = $this->redactor->redactTrace($trace);
            }
        }

        $sqlstate = $this->pullString($context, 'sqlstate') ?? $this->pullString($extra, 'sqlstate');
        $driverCode = $this->pull($context, 'driver_code') ?? $this->pull($extra, 'driver_code');
        if ($sqlstate !== null) {
            $payload['sqlstate'] = $sqlstate;
        }
        if (is_int($driverCode) || (is_string($driverCode) && $driverCode !== '')) {
            $payload['driver_code'] = (string) $driverCode;
        }

        $merged = array_merge($extra, $context);
        $normalized = $this->redactor->redactValue($this->normalize($merged));
        if (! is_array($normalized) || $normalized === []) {
            $payload['context'] = new stdClass;
        } else {
            $payload['context'] = $normalized;
        }

        return $this->shrink($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyException(array &$payload, Throwable $exception): void
    {
        if ($exception instanceof QueryException) {
            $payload['message'] = $this->databaseSummary($exception);
            $sqlstate = $this->sqlstate($exception);
            $driverCode = $this->driverCode($exception);
            if ($sqlstate !== null) {
                $payload['sqlstate'] = $sqlstate;
            }
            if ($driverCode !== null) {
                $payload['driver_code'] = $driverCode;
            }
        }

        $payload['exception_class'] = $exception::class;
        $payload['exception_message'] = $this->redactor->redactString(
            $exception instanceof QueryException ? $payload['message'] : $exception->getMessage(),
        );
        $payload['file'] = $this->relativePath($exception->getFile());
        $payload['line'] = $exception->getLine();
        $payload['stack_trace'] = $this->frames($exception);
    }

    private function databaseSummary(QueryException $exception): string
    {
        $sqlstate = $this->sqlstate($exception);
        $driverCode = $this->driverCode($exception);
        $summary = 'Database query failed';
        if ($sqlstate !== null) {
            $summary .= ' ('.$sqlstate.($driverCode !== null ? ' '.$driverCode : '').')';
        }

        return $summary;
    }

    private function sqlstate(QueryException $exception): ?string
    {
        $info = $exception->errorInfo ?? null;
        if (! is_array($info) || ! isset($info[0]) || ! is_string($info[0]) || $info[0] === '') {
            return null;
        }

        return $info[0];
    }

    private function driverCode(QueryException $exception): ?string
    {
        $info = $exception->errorInfo ?? null;
        if (! is_array($info) || ! isset($info[1]) || $info[1] === '' || $info[1] === null) {
            return null;
        }

        return (string) $info[1];
    }

    private function frames(Throwable $exception): string
    {
        $lines = [];
        foreach (array_slice($exception->getTrace(), 0, self::MAX_FRAMES) as $frame) {
            $file = $this->relativePath((string) ($frame['file'] ?? '[internal]'));
            $line = (int) ($frame['line'] ?? 0);
            $lines[] = $file.':'.$line;
        }

        $text = implode("\n", $lines);
        if (strlen($text) <= self::MAX_TRACE) {
            return $text;
        }

        return substr($text, 0, self::MAX_TRACE).'…';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function copyLabel(array &$payload, string $field, ?string $value, int $max, bool $identifier): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        if ($clean === '') {
            return;
        }

        if ($identifier && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $clean) !== 1) {
            return;
        }

        if (strlen($clean) > $max) {
            $clean = substr($clean, 0, $max);
        }

        $payload[$field] = $clean;
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function pullString(array &$bag, string $key): ?string
    {
        $value = $this->pull($bag, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function pull(array &$bag, string $key): mixed
    {
        if (! array_key_exists($key, $bag)) {
            return null;
        }

        $value = $bag[$key];
        unset($bag[$key]);

        return $value;
    }

    private function relativePath(string $path): string
    {
        $base = function_exists('base_path') ? base_path() : '';
        if ($base !== '' && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/\\');
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function shrink(array $payload): array
    {
        $encoded = $this->toJson($payload, true);
        if (is_string($encoded) && strlen($encoded) <= self::MAX_LINE) {
            return $payload;
        }

        if (isset($payload['stack_trace']) && is_string($payload['stack_trace'])) {
            $payload['stack_trace'] = substr($payload['stack_trace'], 0, 1000).'…';
        }
        $payload['context'] = ['_truncated' => true];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeLine(array $payload): string
    {
        $encoded = $this->toJson($payload, true);
        if (! is_string($encoded) || ! str_starts_with($encoded, '{')) {
            $encoded = $this->toJson($this->fallback('log.encode_failed'), true);
        }

        return $encoded."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(string $eventName): array
    {
        return [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'ERROR',
            'message' => $eventName,
            'event_name' => $eventName,
            'service' => $this->service,
            'environment' => $this->environment,
            'log_channel' => $this->logChannel,
            'context' => new stdClass,
        ];
    }
}
