# Журналы и сборщик ошибок

## Назначение

Пишет диагностические события в файлы JSONL через штатный журнал Laravel и Monolog. Отдельный сборщик группирует одинаковые ошибки и отдаёт короткую выборку агенту. Сборщик не заменяет журналы ВКонтакте по FTP и не подключается к внешнему хранилищу.

Ожидаемые отказы проверки полей и обычные HTTP-ответы в журнал ошибок не попадают: их по-прежнему отсекает обработчик исключений Laravel.

## Поток

```
Log::channel(...) / report(исключение)
    ↓
Маскирование секретов, request_id или operation_id из контекста Laravel
    ↓
Одна JSON-запись на строку в файле канала
    ↓
php artisan logs:query или MCP query_logs
    ↓
Группы, примеры и, при request_id, события этого запроса
```

## Каналы

**Файл:** `config/logging.php`

Файловые каналы `single`, `daily`, `job`, `vk`, `vk-reposts`, `vk-sync`, `vk-images`, `check_tokens`, `stats`, `llm` пишут JSONL. Имена файлов и ротация по суткам не изменились. Сбой записи в файл не прерывает исходную ошибку.

Канал `vk-reposts` заменяет прежний `vkRepost`. Файл: `storage/logs/vk/reposts/error-YYYY-MM-DD.log`. Туда пишутся и частичный провал репоста в группу, и ошибка всего вызова. Пока таких записей не было, файла нет: успешный репост остаётся в канале `job`.

`slack`, `stderr`, `papertrail`, `syslog`, `errorlog` остаются в прежнем текстовом виде. Аварийный файл `storage/logs/laravel.log` тоже текстовый: он используется, только если канал не удалось открыть.

## Запись

**Файл:** `app/Logging/StructuredJsonFormatter.php`

`format(LogRecord $record): string` — одна строка JSON и перевод строки.

Обязательные поля: `timestamp` (UTC, ISO 8601), `level`, `message`, `event_name`, `service`, `environment`, `log_channel`, `context`.

`event_name` берётся из контекста вызова. Если его нет или он не похож на стабильное имя, пишется `app.log`. Меняющиеся значения остаются в `message` и `context`.

Необязательные поля добавляются только когда они есть: `request_id`, `operation_id`, `trace_id`, `span_id`, `component`, `operation`, `route`, `http_method`, `http_status`, `duration_ms`, `release`, `commit_sha`, `exception_class`, `exception_message`, `stack_trace`, `file`, `line`, `sqlstate`, `driver_code`.

`release` — текущая версия из `config/changelog.php`. `commit_sha` пишется только если задан `APP_COMMIT_SHA`. `trace_id` сам по себе не создаётся.

**Файл:** `app/Logging/SensitiveDataRedactor.php`

`redactValue(mixed $value, int $depth = 0, ?string $key = null): mixed` — маскирует ключи паролей, токенов, cookie и сессий, обрезает длинные строки и глубокие массивы.

`redactString(string $value): string` — маскирует заголовок Bearer, токен ВКонтакте и хвост сообщения с текстом SQL.

`redactTrace(string $value): string` — то же для текста стека, плюс значения в кавычках.

**Файл:** `app/Logging/ConfigureStructuredLogging.php`

`__invoke(Logger $logger, string $logChannel = ''): void` — вешает форматтер и процессор на канал. Имя канала передаётся в настройке `tap` после двоеточия.

## Корреляция

**Файл:** `app/Http/Middleware/AssignRequestId.php`

`handle(Request $request, Closure $next): Response` — берёт `X-Request-Id`, если это 8–128 символов из латиницы, цифр и `._:-`. Иначе создаёт UUID. Кладёт `request_id` в контекст Laravel и в заголовок ответа. Тело ответа не меняет.

`terminate(Request $request, Response $response): void` — очищает контекст после запроса.

**Файл:** `app/Logging/OperationContext.php`

`registerJobBoundary(): void` — до выполнения задания очереди запоминает контекст sync-соединения, после задания возвращает его. У задания другого соединения контекст после выполнения очищается.

`beforeJob(JobProcessing $event): void` — готовит идентификатор выполнения.

`onContextHydrated(Repository $context): void` — пишет в контекст задания `operation_id`, `component = queue` и имя задания. `request_id` исходного запроса, если он был передан вместе с заданием, сохраняется.

`afterJob(JobAttempted $event): void` — восстанавливает или очищает контекст.

`beforeCommand(CommandStarting $event): void` и `afterCommand(): void` — для консольной команды задают `operation_id`, `component = console` и имя команды, затем снимают их. Вложенная команда не затирает контекст внешней.

Маршрут и метод HTTP дописываются при сопоставлении маршрута: `component = http`, `route`, `http_method`.

> ВАЖНО: не хранить идентификатор запроса в своём singleton. Контекст Laravel живёт в рамках запроса, задания или команды и очищается на их границе.

## Исключения

Необработанное исключение по-прежнему пишет штатный обработчик Laravel. Повтор того же экземпляра в одном запросе второй раз не пишется.

В контекст такой записи кладётся `event_name = exception.reported`. Для ошибки запроса к базе добавляются `sqlstate` и `driver_code`. Текст SQL и подставленные значения в сообщение не входят. Кадр стека содержит файл и строку, без аргументов вызова.

## Сборщик

**Файл:** `app/Logging/LogQuery.php`

`query(iterable $records, array $options = []): array` — группирует уже прочитанные записи.

`scan(string $directory, array $options = []): array` — читает `*.log` в каталоге и вызывает `query`. Строки не в JSON пропускает и считает в `skipped_lines`.

`fingerprint(array $record): string` — короткий отпечаток группы: имя события, класс исключения, уровень и текст сообщения, в котором числа заменены на `#`.

Фильтры: `from`, `to` (ISO 8601, UTC), `level` (минимальный), `event_name`, `request_id`, `component`, `log_channel`. Без `request_id` и без уровня в группы попадают `WARNING` и выше. С `request_id` и без уровня возвращаются все уровни этого запроса, в том числе в поле `events`. Без интервала берутся последние 24 часа.

Ответ содержит `groups`: `fingerprint`, `event_name`, `exception_class`, `level`, `message`, `count`, `first_seen`, `last_seen`, `is_new`, до двух `samples`. `is_new` равен `null`, если список `known_fingerprints` не передан; `false`, если отпечаток в списке; иначе `true`.

`truncated = true` и `truncation_reason`:

| reason | Когда |
|---|---|
| `limit` | групп или событий больше `limit` |
| `max_bytes` | JSON ответа больше `max_bytes` |
| `scan_limit` | просмотрено больше `max_lines` строк, счётчики могут быть неполными |
| `group_limit` | разных отпечатков больше 300 |

`limit` от 1 до 100, по умолчанию 30. `max_bytes` от 1000 до 150000, по умолчанию 60000. `max_lines` от 1 до 50000, по умолчанию 20000.

Имя файла с датой `YYYY-MM-DD` отбирается по суткам Москвы, потому что так называются суточные файлы. Отбор строк внутри файла идёт по полю `timestamp` в UTC.

### logs:query

**Файл:** `app/Console/Commands/QueryLogsCommand.php`

`handle(LogQuery $query): int` — печатает JSON в stdout и ничего больше. Код `0` при успехе, `1` при неверных аргументах; тогда в stdout объект с полем `error`.

| Опция | Поле сборщика |
|---|---|
| `--from`, `--to` | интервал |
| `--level` | минимальный уровень |
| `--event` | `event_name` |
| `--request` | `request_id` |
| `--component` | компонент |
| `--channel` | `log_channel` |
| `--limit`, `--max-bytes`, `--max-lines` | пределы |
| `--known` | отпечатки через запятую |
| `--dir` | каталог вместо `storage/logs` |

### MCP query_logs

**Файл:** `tools/log-collector/server.mjs`

Инструмент `query_logs` вызывает `php artisan logs:query` в корне проекта. Аргументы те же, каталог снаружи не задаётся: читается `storage/logs` этого приложения. Ответ — JSON сборщика. При ошибке аргументов или запуска текст ошибки и признак ошибки инструмента.

Прежний инструмент `summarize_prod_logs` по-прежнему читает четыре журнала ВКонтакте по FTP. Он понимает и старые текстовые строки, и JSONL, и сохраняет прежнюю форму сводки.

## Ограничение

> ВАЖНО: в вызов журнала не передавать токен, пароль, cookie, SQL с подставленными значениями и полный запрос. Маскирование — защита на случай ошибки, а не разрешение такие данные логировать.
