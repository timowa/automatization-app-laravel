# Analysis — Рефакторинг системы логирования

## Task overview

Три взаимосвязанные проблемы: плоская структура логов, дублирование ошибок по всем каналам, permission denied в Docker. Требуется переработать конфигурацию logging.php, .env и Dockerfile.

## Current architecture

### Конфигурация (.env)

```
LOG_CHANNEL=stack
LOG_STACK=single,daily,vk,vkRepost,vk-sync,check_tokens,stats,job,debug
LOG_LEVEL=debug
```

`stack` — драйвер, который пишет во все каналы из `LOG_STACK` одновременно. Указан несуществующий канал `debug`.

### Каналы (config/logging.php)

| Канал | Драйвер | Путь | Level |
|---|---|---|---|
| `stack` | stack | → single,daily,vk,... | debug |
| `single` | single | `logs/laravel.log` | debug |
| `daily` | daily | `logs/laravel-YYYY-MM-DD.log` | debug |
| `vk` | daily | `logs/vk-YYYY-MM-DD.log` | debug |
| `vkRepost` | daily | `logs/vkRepost-YYYY-MM-DD.log` | debug |
| `vk-sync` | daily | `logs/vk-sync-YYYY-MM-DD.log` | debug |
| `check_tokens` | daily | `logs/check_tokens-YYYY-MM-DD.log` | debug |
| `stats` | daily | `logs/stats-YYYY-MM-DD.log` | debug |
| `job` | daily | `logs/job-YYYY-MM-DD.log` | debug |
| `emergency` | — | `logs/laravel.log` | — |

Все каналы имеют `level: debug` — пишут всё, включая info. Нет фильтрации по level.

### Использование каналов в коде

- `Log::channel('job')->info/warning` — основной канал (ReceiveOfferWebhookAction, ProcessOfferListener, все Jobs, AgentController, SyncVkUserAction, PublishLoopStoriesCommand).
- `Log::channel('vk')->error` — детальные VK-ошибки (все Jobs, VkApiService).
- `Log::channel('vkRepost')->warning` — репосты (CreateVkRepostJob).
- `Log::channel('vk-sync')->info/warning/error` — синхронизация VK (SyncVkUserAction, SyncVkUsersCommand).
- `Log::channel('check_tokens')->info/error` — проверка токенов (CheckTokensCommand).
- `Log::channel('stats')->info` — статистика (GetStatsCommand).

### Как работают каналы сейчас

1. Явный вызов `Log::channel('job')->info(...)` — пишет только в канал `job`. Это работает корректно.
2. Необработанные исключения (ExceptionHandler) — пишут через default channel = `stack` → дублируются во ВСЕ каналы стека. Это и есть проблема #2.
3. Все каналы имеют `level: debug` — пишут info/warning/error без фильтрации. Нет разделения "только ошибки" и "только успех/неудача".

## Affected areas

- `config/logging.php` — полная переработка.
- `.env` — `LOG_STACK`, возможно `LOG_CHANNEL`.
- `Dockerfile` — права на storage/logs.
- `docker-compose.yml` — возможно, volume для логов.
- Код приложения — НЕ затрагивается (вызовы `Log::channel()` остаются).

## Existing implementation

### Доказательство дублирования

Файлы на 2026-08-13:
- `laravel-2026-08-13.log` — 99 строк
- `job-2026-08-13.log` — 115 строк (больше, потому что есть testing-записи)
- `vk-2026-08-13.log` — идентичен `job` (diff пуст)
- `vkRepost-2026-08-13.log` — идентичен
- `stats-2026-08-13.log` — идентичен
- `check_tokens-2026-08-13.log` — идентичен
- `vk-sync-2026-08-13.log` — идентичен

Все файлы содержат одну и ту же migration-ошибку:
```
SQLSTATE[HY000]: General error: 1824 Failed to open the referenced table 'publication_tasks'
```
И permission-denied ошибки. Эти ошибки не имеют отношения к конкретному каналу, но пишутся везде.

### Доказательство permission denied

`laravel.log` (12 МБ): 760+ записей "Permission denied". Ошибка возникает при попытке открыть `laravel-2026-08-13.log` в append-режиме.

Причина:
- Docker: `php:8.4-fpm` работает от `www-data` (UID 33).
- docker-compose монтирует `./storage/logs:/var/www/html/storage/logs`.
- Файлы на хосте (Windows) принадлежат `timowa`, права `644` (rw-r--r--).
- `www-data` не владелец, group/others не имеют прав на запись → Permission denied.
- Ошибка каскадно размножается: попытка залогировать ошибку логирования тоже падает.

### Текущая структура логов

```
storage/logs/
├── laravel.log              (12 МБ — не ротируется, single-канал)
├── laravel-2026-08-13.log   (ротация daily)
├── job-2026-08-13.log       (идентичен остальным)
├── vk-2026-08-13.log        (идентичен)
├── vkRepost-2026-08-13.log  (идентичен)
├── vk-sync-2026-08-13.log   (идентичен)
├── check_tokens-2026-08-13.log (идентичен)
├── stats-2026-08-13.log     (идентичен)
```

## Architecture decisions

### Решение 1: Разделить stack и default channel

Default channel (`LOG_CHANNEL`) должен писать только в `laravel.log` (через `single` или `daily`). Не в stack с всеми каналами.

```
LOG_CHANNEL=single
```

Удалить `LOG_STACK` или заменить на `LOG_STACK=single` — необработанные исключения пишутся только в laravel.log.

Явные вызовы `Log::channel('job')`, `Log::channel('vk')` и т.д. продолжают работать как раньше — пишут в свои каналы.

### Решение 2: Иерархия логов VK-каналов

Изменить пути в `config/logging.php` для VK-каналов:

| Канал | Текущий путь | Новый путь |
|---|---|---|
| `vk` | `logs/vk-YYYY-MM-DD.log` | `logs/vk/posts/error-YYYY-MM-DD.log` |
| `vkRepost` | `logs/vkRepost-YYYY-MM-DD.log` | `logs/vk/reposts/error-YYYY-MM-DD.log` |
| `vk-sync` | `logs/vk-sync-YYYY-MM-DD.log` | `logs/vk/sync/error-YYYY-MM-DD.log` |
| `check_tokens` | `logs/check_tokens-YYYY-MM-DD.log` | `logs/vk/tokens/error-YYYY-MM-DD.log` |
| `stats` | `logs/stats-YYYY-MM-DD.log` | `logs/vk/stats/error-YYYY-MM-DD.log` |
| `job` | `logs/job-YYYY-MM-DD.log` | `logs/job-YYYY-MM-DD.log` (без изменений) |

Драйвер `daily` создаёт поддиректории автоматически при первом写入.

> Имя файла `error-YYYY-MM-DD.log` — определяется драйвером daily: берёт basename от path и добавляет дату. Например `storage_path('logs/vk/posts/error.log')` → `error-2026-08-13.log`.

### Решение 3: Level-фильтрация каналов

| Канал | Level | Что пишется |
|---|---|---|
| `job` | `info` | info, warning (успех задач, краткие неудачи) |
| `vk` (posts) | `warning` | warning, error (детальные ошибки VK API) |
| `vkRepost` | `warning` | warning (ошибки репостов) |
| `vk-sync` | `info` | info, warning, error (полный лог синхронизации) |
| `check_tokens` | `info` | info, error (результат проверки) |
| `stats` | `info` | info (результат сбора) |
| `single` (laravel.log) | `debug` | все уровни (необработанные исключения) |

> Внимание: `level` в Monolog означает "минимальный уровень для записи". `info` = info+warning+error. `warning` = warning+error. `error` = только error.

### Решение 4: Права в Docker

Проблема — владелец/права на `storage/logs/`. Варианты:

**Вариант A (Dockerfile):** добавить в Dockerfile создание директорий с правильными правами:
```dockerfile
RUN mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats \
    && chown -R www-data:www-data /var/www/html/storage/logs \
    && chmod -R 775 /var/www/html/storage/logs
```

Не сработает надёжно, потому что volume `./storage/logs` монтируется с хоста и права хоста перебивают.

**Вариант B (docker-compose entrypoint):** entrypoint-скрипт, который перед запуском php-fpm исправляет права:
```bash
chown -R www-data:www-data /var/www/html/storage/logs
chmod -R 775 /var/www/html/storage/logs
```

**Вариант C (Dockerfile + chmod):** запускать php-fpm от root с понижением через `www-data` в команде. Или использовать `user: "33:33"` в docker-compose для контейнера app.

Рекомендуемый — Вариант B: entrypoint-скрипт. Он гарантированно срабатывает при каждом старте контейнера, даже если файлы уже созданы хостом.

### Решение 5: Очистка .env

```
LOG_CHANNEL=single
LOG_STACK=single
```

Или удалить `LOG_STACK` совсем (default = `single` из config). Убрать несуществующий `debug` канал.

## Dependencies

```
1. Dockerfile / docker-compose — права доступа (проблема #3)
2. config/logging.php — структура каналов (проблемы #1, #2)
3. .env — LOG_CHANNEL, LOG_STACK (проблема #2)
```

Порядок выполнения:
1. Docker (entrypoint + права) — чтобы логи могли писаться
2. config/logging.php — структура каналов
3. .env — переключение LOG_CHANNEL

Все три части можно делать последовательно одним агентом.

## Risks

1. **Существующие логи при ротации** — при переходе на новую структуру старые файлы в `storage/logs/` останутся. Нужно ли их удалять? Решение: оставить, не трогать.

2. **Драйвер daily и поддиректории** — драйвер `daily` создаёт файл `error-YYYY-MM-DD.log` по пути из `path`. Если поддиректория не существует, Monolog должен создать её автоматически (через `mkdir` в StreamHandler). Проверить.

3. **chmod/chown в entrypoint** — может не сработать на Windows-хосте (NTFS не поддерживает Unix-права в volume-монтировании). На Linux/Docker Desktop — работает.

4. **Удаление `debug` канала из LOG_STACK** — если какой-то код явно вызывает `Log::channel('debug')`, упадёт. Проверить: grep не нашёл использования `debug` канала в коде.

5. **Тесты** — тесты используют `testing` окружение. Проверить `phpunit.xml` — какие log-каналы в testing. Если `LOG_CHANNEL=stack` в testing, тесты могут дублировать.

## Questions

Нет. Информации достаточно.

## Definition of Done

- [ ] VK-каналы пишут в `storage/logs/vk/<channel>/error-YYYY-MM-DD.log`
- [ ] Канал `job` содержит только info/warning (успех/неудача задач)
- [ ] VK-каналы содержат только warning/error (детальные ошибки)
- [ ] Ошибки приложения пишутся только в `laravel.log`, не дублируются в остальные каналы
- [ ] `LOG_STACK` не содержит несуществующий `debug` канал
- [ ] Права на `storage/logs/` в Docker позволяют `www-data` писать логи
- [ ] Ошибка "Permission denied" не воспроизводится при запросе к `/offer`