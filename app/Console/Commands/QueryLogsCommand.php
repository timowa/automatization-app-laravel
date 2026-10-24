<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Logging\LogQuery;
use Illuminate\Console\Command;
use InvalidArgumentException;

class QueryLogsCommand extends Command
{
    protected $signature = 'logs:query
        {--from= : Начало интервала, ISO 8601, UTC}
        {--to= : Конец интервала, ISO 8601, UTC}
        {--level= : Минимальный уровень: debug, info, notice, warning, error, critical, alert, emergency}
        {--event= : Точное event_name}
        {--request= : request_id}
        {--component= : component}
        {--channel= : log_channel}
        {--limit=30 : Сколько групп и событий вернуть, от 1 до 100}
        {--max-bytes=60000 : Максимальный размер JSON-ответа}
        {--max-lines=20000 : Сколько строк журнала просмотреть}
        {--known= : Известные отпечатки через запятую. Без опции признак новизны не считается}
        {--dir= : Каталог журналов, по умолчанию storage/logs}';

    protected $description = 'Группы ошибок и выборка событий из JSONL-журналов';

    public function handle(LogQuery $query): int
    {
        $known = $this->option('known');
        $fingerprints = null;
        if ($known !== null) {
            $fingerprints = array_values(array_filter(
                array_map('trim', explode(',', (string) $known)),
                static fn (string $item): bool => $item !== '',
            ));
        }

        try {
            $result = $query->scan($this->directory(), [
                'from' => $this->option('from') ?: null,
                'to' => $this->option('to') ?: null,
                'level' => $this->option('level') ?: null,
                'event_name' => $this->option('event') ?: null,
                'request_id' => $this->option('request') ?: null,
                'component' => $this->option('component') ?: null,
                'log_channel' => $this->option('channel') ?: null,
                'limit' => $this->option('limit'),
                'max_bytes' => $this->option('max-bytes'),
                'max_lines' => $this->option('max-lines'),
                'known_fingerprints' => $fingerprints,
                'window' => ($this->option('from') || $this->option('to')) ? 'none' : '24h',
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->output->write((string) json_encode([
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->output->write((string) json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return self::SUCCESS;
    }

    private function directory(): string
    {
        $dir = $this->option('dir');
        if (! is_string($dir) || $dir === '') {
            return storage_path('logs');
        }

        return $dir;
    }
}
