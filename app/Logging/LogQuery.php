<?php

declare(strict_types=1);

namespace App\Logging;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use SplFileObject;
use Symfony\Component\Finder\Finder;

/**
 * Выборка и группировка JSONL-журналов для разбора ошибок.
 * Не отдаёт файл целиком: группы, короткие примеры и события одного запроса.
 */
final class LogQuery
{
    private const MAX_LIMIT = 100;

    private const MAX_BYTES = 150000;

    private const MAX_LINES = 50000;

    private const MAX_TRACKED_GROUPS = 300;

    /** @var array<string, int> */
    private const LEVEL_RANK = [
        'DEBUG' => 10,
        'INFO' => 20,
        'NOTICE' => 30,
        'WARNING' => 40,
        'ERROR' => 50,
        'CRITICAL' => 60,
        'ALERT' => 70,
        'EMERGENCY' => 80,
    ];

    public function __construct(private readonly SensitiveDataRedactor $redactor = new SensitiveDataRedactor) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function query(iterable $records, array $options = []): array
    {
        $filters = $this->filters($options);
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $untracked = 0;
        $matched = 0;
        $scanned = 0;
        $events = [];
        $eventsTruncated = false;

        foreach ($records as $record) {
            $scanned++;
            if (! $this->matches($record, $filters)) {
                continue;
            }
            $matched++;
            $fingerprint = self::fingerprint($record);
            if (! isset($groups[$fingerprint])) {
                if (count($groups) >= self::MAX_TRACKED_GROUPS) {
                    $untracked++;

                    continue;
                }
                $groups[$fingerprint] = $this->newGroup($record, $fingerprint, $filters['known']);
            }
            $this->accumulate($groups[$fingerprint], $record);

            if ($filters['request_id'] !== null) {
                if (count($events) < $filters['limit']) {
                    $events[] = $this->eventSample($record, false);
                } else {
                    $eventsTruncated = true;
                }
            }
        }

        uasort($groups, function (array $left, array $right): int {
            $byCount = $right['count'] <=> $left['count'];
            if ($byCount !== 0) {
                return $byCount;
            }

            return strcmp((string) $right['last_seen'], (string) $left['last_seen']);
        });

        $totalGroups = count($groups);
        $listed = array_slice(array_values($groups), 0, $filters['limit']);
        $omitted = $untracked + max(0, $totalGroups - count($listed));
        $truncated = $omitted > 0 || $eventsTruncated;
        $reason = null;
        if ($untracked > 0) {
            $reason = 'group_limit';
        } elseif ($omitted > 0 || $eventsTruncated) {
            $reason = 'limit';
        }

        foreach ($listed as &$group) {
            unset($group['first_ms'], $group['last_ms']);
        }
        unset($group);

        $payload = [
            'from' => $filters['from']?->format('Y-m-d\TH:i:s\Z'),
            'to' => $filters['to']?->format('Y-m-d\TH:i:s\Z'),
            'timezone' => 'UTC',
            'filters' => [
                'level' => $filters['level'],
                'event_name' => $filters['event_name'],
                'request_id' => $filters['request_id'],
                'component' => $filters['component'],
                'log_channel' => $filters['log_channel'],
            ],
            'truncated' => $truncated,
            'truncation_reason' => $reason,
            'scanned' => $scanned,
            'skipped_lines' => 0,
            'matched' => $matched,
            'omitted_groups' => $omitted,
            'groups' => $listed,
            'events' => $filters['request_id'] !== null ? $events : null,
        ];

        return $this->fitBytes($payload, $filters['max_bytes']);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function scan(string $directory, array $options = []): array
    {
        if (! is_dir($directory)) {
            throw new InvalidArgumentException('Каталог журналов не найден.');
        }

        $filters = $this->filters($options);
        $state = ['scanned' => 0, 'skipped_lines' => 0, 'limited' => false];
        $result = $this->query($this->read($directory, $filters, $state), $options);
        $result['scanned'] = $state['scanned'];
        $result['skipped_lines'] = $state['skipped_lines'];
        if ($state['limited']) {
            $result['truncated'] = true;
            $result['truncation_reason'] = 'scan_limit';
        }

        return $result;
    }

    public static function fingerprint(array $record): string
    {
        $message = preg_replace('/\d+/', '#', (string) ($record['message'] ?? '')) ?? '';
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? '';
        if (strlen($message) > 180) {
            $message = substr($message, 0, 180);
        }

        $raw = implode('|', [
            (string) ($record['event_name'] ?? ''),
            (string) ($record['exception_class'] ?? ''),
            strtoupper((string) ($record['level'] ?? '')),
            $message,
        ]);

        return substr(hash('sha256', $raw), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function filters(array $options): array
    {
        $limit = (int) ($options['limit'] ?? 30);
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('limit должен быть от 1 до 100.');
        }

        $maxBytes = (int) ($options['max_bytes'] ?? 60000);
        if ($maxBytes < 1000 || $maxBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('max_bytes должен быть от 1000 до 150000.');
        }

        $maxLines = (int) ($options['max_lines'] ?? 20000);
        if ($maxLines < 1 || $maxLines > self::MAX_LINES) {
            throw new InvalidArgumentException('max_lines должен быть от 1 до 50000.');
        }

        $level = $this->nullableString($options['level'] ?? null);
        if ($level !== null) {
            $level = strtoupper($level);
            if (! isset(self::LEVEL_RANK[$level])) {
                throw new InvalidArgumentException('Неизвестный уровень лога.');
            }
        }

        $requestId = $this->nullableString($options['request_id'] ?? null);
        $known = $options['known_fingerprints'] ?? null;
        if ($known !== null && ! is_array($known)) {
            throw new InvalidArgumentException('known_fingerprints должен быть списком.');
        }
        if (is_array($known)) {
            foreach ($known as $fingerprint) {
                if (! is_string($fingerprint) || preg_match('/^[a-f0-9]{8,64}$/', $fingerprint) !== 1) {
                    throw new InvalidArgumentException('Отпечаток ошибки должен быть шестнадцатеричной строкой.');
                }
            }
        }

        $from = $this->time($options['from'] ?? null, 'from');
        $to = $this->time($options['to'] ?? null, 'to');
        if ($from === null && $to === null && ($options['window'] ?? '24h') !== 'none') {
            $to = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $from = $to->sub(new DateInterval('PT24H'));
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('Начало интервала позже конца.');
        }

        return [
            'from' => $from,
            'to' => $to,
            'level' => $level,
            'min_level' => $level ?? ($requestId !== null ? 'DEBUG' : 'WARNING'),
            'event_name' => $this->nullableString($options['event_name'] ?? null),
            'request_id' => $requestId,
            'component' => $this->nullableString($options['component'] ?? null),
            'log_channel' => $this->nullableString($options['log_channel'] ?? null),
            'known' => $known,
            'limit' => $limit,
            'max_bytes' => $maxBytes,
            'max_lines' => $maxLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{scanned: int, skipped_lines: int, limited: bool}  $state
     * @return iterable<int, array<string, mixed>>
     */
    private function read(string $directory, array $filters, array &$state): iterable
    {
        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->name('*.log')
            ->ignoreUnreadableDirs()
            ->sortByName();

        $seen = 0;
        foreach ($finder as $file) {
            $path = $file->getPathname();
            if (! $this->fileOverlaps($path, $filters['from'], $filters['to'])) {
                continue;
            }

            $handle = new SplFileObject($path, 'r');
            while (! $handle->eof()) {
                $line = $handle->fgets();
                if ($line === false || trim($line) === '') {
                    continue;
                }
                $seen++;
                if ($seen > $filters['max_lines']) {
                    $state['limited'] = true;

                    return;
                }

                $record = $this->parseLine($line);
                if ($record === null) {
                    $state['skipped_lines']++;

                    continue;
                }
                $state['scanned']++;
                yield $record;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || ! str_starts_with($line, '{')) {
            return null;
        }

        try {
            $decoded = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || ! is_string($decoded['message'] ?? null) || ! is_string($decoded['level'] ?? null)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $record, array $filters): bool
    {
        $level = strtoupper((string) ($record['level'] ?? ''));
        if (! isset(self::LEVEL_RANK[$level], self::LEVEL_RANK[$filters['min_level']])) {
            return false;
        }
        if (self::LEVEL_RANK[$level] < self::LEVEL_RANK[$filters['min_level']]) {
            return false;
        }

        $ms = $this->timestampMs($record);
        if ($filters['from'] instanceof DateTimeImmutable || $filters['to'] instanceof DateTimeImmutable) {
            if ($ms === null) {
                return false;
            }
            if ($filters['from'] instanceof DateTimeImmutable && $ms < $filters['from']->getTimestamp()) {
                return false;
            }
            if ($filters['to'] instanceof DateTimeImmutable && $ms > $filters['to']->getTimestamp()) {
                return false;
            }
        }

        if ($filters['event_name'] !== null && ($record['event_name'] ?? null) !== $filters['event_name']) {
            return false;
        }
        if ($filters['request_id'] !== null && ($record['request_id'] ?? null) !== $filters['request_id']) {
            return false;
        }
        if ($filters['component'] !== null && ($record['component'] ?? null) !== $filters['component']) {
            return false;
        }
        if ($filters['log_channel'] !== null && ($record['log_channel'] ?? null) !== $filters['log_channel']) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $known
     * @return array<string, mixed>
     */
    private function newGroup(array $record, string $fingerprint, ?array $known): array
    {
        return [
            'fingerprint' => $fingerprint,
            'event_name' => is_string($record['event_name'] ?? null) ? $record['event_name'] : null,
            'exception_class' => is_string($record['exception_class'] ?? null) ? $record['exception_class'] : null,
            'level' => strtoupper((string) $record['level']),
            'message' => (string) $record['message'],
            'count' => 0,
            'first_seen' => null,
            'last_seen' => null,
            'first_ms' => null,
            'last_ms' => null,
            'is_new' => $known === null ? null : ! in_array($fingerprint, $known, true),
            'samples' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $record
     */
    private function accumulate(array &$group, array $record): void
    {
        $group['count']++;
        $seen = is_string($record['timestamp'] ?? null) ? $record['timestamp'] : null;
        $ms = $this->timestampMs($record);
        if ($ms !== null && ($group['first_ms'] === null || $ms < $group['first_ms'])) {
            $group['first_ms'] = $ms;
            $group['first_seen'] = $seen;
        }
        if ($ms !== null && ($group['last_ms'] === null || $ms >= $group['last_ms'])) {
            $group['last_ms'] = $ms;
            $group['last_seen'] = $seen;
        }
        if (count($group['samples']) < 2) {
            $group['samples'][] = $this->eventSample($record, count($group['samples']) === 0);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function eventSample(array $record, bool $withTrace): array
    {
        $sample = [
            'timestamp' => is_string($record['timestamp'] ?? null) ? $record['timestamp'] : null,
            'level' => strtoupper((string) ($record['level'] ?? '')),
            'message' => (string) ($record['message'] ?? ''),
            'event_name' => is_string($record['event_name'] ?? null) ? $record['event_name'] : null,
            'request_id' => is_string($record['request_id'] ?? null) ? $record['request_id'] : null,
            'operation_id' => is_string($record['operation_id'] ?? null) ? $record['operation_id'] : null,
            'component' => is_string($record['component'] ?? null) ? $record['component'] : null,
            'log_channel' => is_string($record['log_channel'] ?? null) ? $record['log_channel'] : null,
            'exception_class' => is_string($record['exception_class'] ?? null) ? $record['exception_class'] : null,
            'file' => is_string($record['file'] ?? null) ? $record['file'] : null,
            'line' => is_int($record['line'] ?? null) ? $record['line'] : null,
            'context' => $this->briefContext($record['context'] ?? null),
        ];
        if ($withTrace && is_string($record['stack_trace'] ?? null) && $record['stack_trace'] !== '') {
            $trace = $this->redactor->redactTrace($record['stack_trace']);
            $sample['stack_trace'] = strlen($trace) > 500 ? substr($trace, 0, 500).'…' : $trace;
        }

        return $sample;
    }

    /**
     * @return array<string, mixed>
     */
    private function briefContext(mixed $context): array
    {
        if (! is_array($context)) {
            return [];
        }

        $safe = $this->redactor->redactValue($context);
        if (! is_array($safe)) {
            return [];
        }

        unset($safe['trace'], $safe['stack_trace'], $safe['exception']);
        $brief = [];
        foreach ($safe as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (count($brief) >= 8) {
                break;
            }
            if (is_string($value) && strlen($value) > 160) {
                $value = substr($value, 0, 160).'…';
            } elseif (is_array($value)) {
                $value = '[array]';
            } elseif (is_object($value)) {
                continue;
            }
            $brief[$key] = $value;
        }

        return $brief;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fitBytes(array $payload, int $maxBytes): array
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $trimmed = false;
        while (true) {
            $encoded = json_encode($payload, $flags);
            if (! is_string($encoded) || strlen($encoded) <= $maxBytes) {
                break;
            }
            $trimmed = true;
            if ($this->dropSample($payload['groups'])) {
                continue;
            }
            if (is_array($payload['events']) && $payload['events'] !== []) {
                array_pop($payload['events']);

                continue;
            }
            if (is_array($payload['groups']) && count($payload['groups']) > 0) {
                array_pop($payload['groups']);
                $payload['omitted_groups']++;

                continue;
            }
            break;
        }

        if ($trimmed) {
            $payload['truncated'] = true;
            if ($payload['truncation_reason'] === null) {
                $payload['truncation_reason'] = 'max_bytes';
            }
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function dropSample(array &$groups): bool
    {
        foreach ($groups as &$group) {
            if (($group['samples'] ?? []) !== []) {
                array_pop($group['samples']);
                unset($group);

                return true;
            }
        }
        unset($group);

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function timestampMs(array $record): ?int
    {
        $value = $record['timestamp'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }

    private function fileOverlaps(string $path, ?DateTimeImmutable $from, ?DateTimeImmutable $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($path), $match) !== 1) {
            return true;
        }

        $start = strtotime($match[1].' 00:00:00 +0300');
        $end = strtotime($match[1].' 23:59:59 +0300');
        if ($start === false || $end === false) {
            return true;
        }
        if ($from !== null && $end < $from->getTimestamp()) {
            return false;
        }
        if ($to !== null && $start > $to->getTimestamp()) {
            return false;
        }

        return true;
    }

    private function time(mixed $value, string $name): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException($name.' должен быть строкой ISO 8601.');
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidArgumentException($name.' должен быть строкой ISO 8601.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Фильтр должен быть строкой.');
        }

        return $value;
    }
}
