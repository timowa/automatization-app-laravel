# Backend Task — Рефакторинг системы логирования

## Цель

Переработать систему логирования:
1. Создать иерархию `storage/logs/vk/<channel>/error-YYYY-MM-DD.log` для VK-каналов.
2. Разделить потоки: ошибки приложения → только `laravel.log`; успех/неудача задач → канал `job`; детальные ошибки → VK-каналы.
3. Исправить permission denied при записи логов в Docker.

## Контекст

### Текущая конфигурация .env (проблема)
```
LOG_CHANNEL=stack
LOG_STACK=single,daily,vk,vkRepost,vk-sync,check_tokens,stats,job,debug
LOG_LEVEL=debug
```

`stack` пишет во все каналы одновременно. Необработанные исключения дублируются во все файлы. `debug` — несуществующий канал.

### Текущая конфигурация config/logging.php

Все каналы — `driver: daily`, `level: debug`, пути плоские в `storage/logs/`.

### Docker-окружение

- `php:8.4-fpm` работает от `www-data` (UID 33).
- `docker-compose.yml` монтирует `./storage/logs:/var/www/html/storage/logs`.
- Файлы на хосте принадлежат `timowa`, права `644` — `www-data` не может писать.
- В `laravel.log` 760+ записей "Permission denied".

### Использование Log::channel() в коде

Код приложения НЕ изменяется. Вызовы остаются:
- `Log::channel('job')->info/warning` — успех/неудача задач
- `Log::channel('vk')->error` — детальные VK-ошибки
- `Log::channel('vkRepost')->warning` — ошибки репостов
- `Log::channel('vk-sync')->info/warning/error` — синхронизация
- `Log::channel('check_tokens')->info/error` — проверка токенов
- `Log::channel('stats')->info` — статистика

## Существующая реализация

config/logging.php — 7 кастомных каналов (vk, vkRepost, vk-sync, check_tokens, stats, job) + стандартные (single, daily, stack, emergency). Все с `level: debug`, плоские пути.

Dockerfile — `php:8.4-fpm`, нет настройки прав на storage/logs. CMD — `php-fpm`.

docker-compose.yml — volume `./storage/logs:/var/www/html/storage/logs`, контейнер `app` работает от root (по умолчанию), но php-fpm пул работает от `www-data`.

## Необходимые изменения

### 1. config/logging.php

Изменить пути и levels для VK-каналов:

```
// Канал job — только info и выше (успех/неудача задач)
'job' => [
    'driver' => 'daily',
    'path' => storage_path('logs/job.log'),  // путь БЕЗ изменений
    'level' => 'info',
    'days' => 7,
    'replace_placeholders' => true,
],

// Канал vk (посты) — warning и выше (детальные ошибки VK API)
'vk' => [
    'driver' => 'daily',
    'path' => storage_path('logs/vk/posts/error.log'),
    'level' => 'warning',
    'days' => 7,
    'replace_placeholders' => true,
],

// Канал vkRepost — warning и выше
'vkRepost' => [
    'driver' => 'daily',
    'path' => storage_path('logs/vk/reposts/error.log'),
    'level' => 'warning',
    'days' => 7,
    'replace_placeholders' => true,
],

// Канал vk-sync — info и выше (полный лог синхронизации)
'vk-sync' => [
    'driver' => 'daily',
    'path' => storage_path('logs/vk/sync/error.log'),
    'level' => 'info',
    'days' => 7,
    'replace_placeholders' => true,
],

// Канал check_tokens — info и выше
'check_tokens' => [
    'driver' => 'daily',
    'path' => storage_path('logs/vk/tokens/error.log'),
    'level' => 'info',
    'days' => 7,
    'replace_placeholders' => true,
],

// Канал stats — info и выше
'stats' => [
    'driver' => 'daily',
    'path' => storage_path('logs/vk/stats/error.log'),
    'level' => 'info',
    'days' => 7,
    'replace_placeholders' => true,
],
```

Канал `stack` — изменить default на `single` только:
```
'stack' => [
    'driver' => 'stack',
    'channels' => explode(',', (string) env('LOG_STACK', 'single')),
    'ignore_exceptions' => false,
],
```

Каналы `single` и `daily` — оставить без изменений (level: debug, path: laravel.log).

> ПРИМЕЧАНИЕ: драйвер `daily` берёт basename из path и добавляет дату. `storage_path('logs/vk/posts/error.log')` → `storage/logs/vk/posts/error-2026-08-13.log`. Поддиректории `vk/posts/`, `vk/reposts/` и т.д. создаются автоматически при первом写入 (Monolog вызывает mkdir).

### 2. .env

Изменить:
```
LOG_CHANNEL=single
LOG_STACK=single
```

Удалить `debug` из LOG_STACK (несуществующий канал). Оставить только `single` — необработанные исключения пишутся только в `laravel.log` (через single) и `laravel-YYYY-MM-DD.log` (через daily, если включён). Можно оставить `LOG_STACK=single` или `LOG_STACK=single,daily` — оба пишут только в laravel-файлы.

### 3. Dockerfile

Добавить создание поддиректорий и настройку прав. Добавить перед CMD:

```
RUN mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats

# Entrypoint для исправления прав при старте контейнера
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]
```

### 4. docker/entrypoint.sh (НОВЫЙ файл)

```bash
#!/bin/sh
set -e

# Исправляем права на storage/logs для www-data
chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true
chmod -R 775 /var/www/html/storage/logs 2>/dev/null || true

# Создаём поддиректории если не существуют
mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats 2>/dev/null || true

# Запускаем php-fpm
exec php-fpm
```

> `2>/dev/null || true` — чтобы не падать если chmod/chown не может изменить права на Windows-хосте (NTFS).

### 5. Очистка laravel.log

Старый `laravel.log` (12 МБ, 760+ permission-denied записей) — удалить или очистить. Это одноразовая операция:
```bash
> storage/logs/laravel.log
```

## Затрагиваемые файлы

- `config/logging.php` — изменение путей и levels каналов.
- `.env` — LOG_CHANNEL, LOG_STACK.
- `Dockerfile` — добавление mkdir, entrypoint.
- `docker/entrypoint.sh` — НОВЫЙ файл.

## API изменения

Нет.

## Модели

Нет.

## Миграции

Нет.

## Тесты

1. Запустить контейнер: `docker compose up -d`
2. Отправить запрос к `/offer`:
```bash
curl --noproxy '*' -X POST http://localhost:8080/offer \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"offers":[{...тестовый оффер...}]}'
```
3. Проверить:
   - `storage/logs/laravel.log` — не содержит "Permission denied"
   - `storage/logs/job-YYYY-MM-DD.log` — содержит "new Offer Request" (info), не содержит детальных stack-trace
   - `storage/logs/vk/posts/error-YYYY-MM-DD.log` — не существует или пустой (если нет VK-ошибок)
   - `storage/logs/vk/posts/` директория создана
   - `storage/logs/vk/reposts/` директория создана
   - Файлы `vk-2026-08-13.log`, `job-2026-08-13.log` (старые, плоские) — не содержат новых записей

## Ограничения

- Не изменять вызовы `Log::channel()` в PHP-коде приложения.
- Не добавлять новые каналы логирования.
- Не изменять логику ExceptionHandler.
- Сохранить драйвер `daily` (ротация по дням, 7 дней).
- Канал `job` остаётся в корне `storage/logs/` (не в `vk/`).

## Out of scope

- Изменение кода Jobs, Actions, Commands (вызовы Log::channel() не меняются).
- Добавление новых лог-каналов.
- Настройка Slack/papertrail.
- Изменение phpunit.xml (тестовое окружение).

## Definition of Done

- [x] config/logging.php: VK-каналы используют пути `logs/vk/<channel>/error.log` с level `warning`/`info`
- [x] config/logging.php: канал `job` имеет level `info`
- [x] .env: LOG_CHANNEL=single, LOG_STACK не содержит `debug`
- [x] Dockerfile: создаются поддиректории `vk/posts`, `vk/reposts`, `vk/sync`, `vk/tokens`, `vk/stats`
- [x] docker/entrypoint.sh: исправляет права на storage/logs при старте
- [x] docker-compose.yml: использует entrypoint (если нужно переопределить command)
- [x] Ошибка "Permission denied" не воспроизводится при запросе к /offer
- [x] Канал `job` не содержит детальных stack-trace ошибок
- [x] Ошибки приложения не дублируются в VK-каналы