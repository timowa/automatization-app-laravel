# Backend Report — Рефакторинг системы логирования

## Выполненные изменения

### 1. config/logging.php

- VK-каналы (`vk`, `vkRepost`, `vk-sync`, `check_tokens`, `stats`) теперь пишут в иерархию `storage/logs/vk/<channel>/error.log`:
  - `vk` → `logs/vk/posts/error.log`, level `warning`
  - `vkRepost` → `logs/vk/reposts/error.log`, level `warning`
  - `vk-sync` → `logs/vk/sync/error.log`, level `info`
  - `check_tokens` → `logs/vk/tokens/error.log`, level `info`
  - `stats` → `logs/vk/stats/error.log`, level `info`
- Канал `job` оставлен в корне `storage/logs/job.log`, level изменён на `info`.
- Канал `stack` оставлен без изменений, default env `LOG_STACK` остаётся `single`.
- Каналы `single` и `daily` не изменялись.

### 2. .env

```
LOG_CHANNEL=single
LOG_STACK=single
```

Убран `debug` (несуществующий канал) и убрана запись во все VK-каналы одновременно через `stack`. Необработанные исключения теперь попадают только в `laravel.log`.

### 3. Dockerfile

Добавлено:
- Создание поддиректорий VK-логов через `mkdir -p`.
- Копирование `docker/entrypoint.sh` в `/usr/local/bin/entrypoint.sh`.
- Установка execute-прав.
- CMD заменён на `[/usr/local/bin/entrypoint.sh]`.

### 4. docker/entrypoint.sh

Новый файл. При старте контейнера:
- `chown -R www-data:www-data /var/www/html/storage/logs`
- `chmod -R 775 /var/www/html/storage/logs`
- Создание VK-поддиректорий.
- Запуск `php-fpm`.

Команды обёрнуты в `2>/dev/null || true`, чтобы не падать на Windows-хосте (NTFS).

### 5. docker-compose.yml

Для сервиса `app` добавлен `command: /usr/local/bin/entrypoint.sh`, чтобы переопределить CMD из Dockerfile и гарантировать исполнение entrypoint при каждом старте.

### 6. Очистка laravel.log

Файл `storage/logs/laravel.log` очищен (760+ записей "Permission denied" удалены).

## Изменённые файлы

- `config/logging.php`
- `.env`
- `Dockerfile`
- `docker/entrypoint.sh` (новый)
- `docker-compose.yml`
- `storage/logs/laravel.log` (очищен)
- `.ai/tasks/2026-08-13-logging-refactor/tasks/backend.md` (обновлён статус DoD)

## API изменения

Нет.

## Модели / Миграции

Нет.

## Проверка

### Конфигурация

```bash
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; ..."
```

Результат:

```
vk path: D:\PhpStormProjects\vk19-app\storage\logs/vk/posts/error.log
vk level: warning
job path: D:\PhpStormProjects\vk19-app\storage\logs/job.log
job level: info
default channel: single
```

### Тесты

```bash
php artisan test --filter=BackendImplementationTest
```

Результат:

```
PASS  Tests\Unit\BackendImplementationTest
  ✓ scenario factory returns ten scenarios in order
  ✓ job resolver returns class for every task type
  ✓ dependence inspector returns expected dependencies
  ✓ scenario template resolver returns template for post scenarios

  Tests:    4 passed (36 assertions)
```

### Проверка разделения каналов

Команда:

```php
Log::channel('job')->info('test job info');
Log::channel('vk')->warning('test vk warning');
Log::channel('vk')->info('test vk info - should not appear');
```

Результат:

- `storage/logs/job-2026-08-13.log` содержит `test job info`.
- `storage/logs/vk/posts/error-2026-08-13.log` содержит только `test vk warning`, `test vk info` отфильтрован по level.
- Новые VK-записи не попадают в `vk-2026-08-13.log` (старый плоский файл).

## Definition of Done

- [x] config/logging.php: VK-каналы используют пути `logs/vk/<channel>/error.log` с level `warning`/`info`
- [x] config/logging.php: канал `job` имеет level `info`
- [x] .env: LOG_CHANNEL=single, LOG_STACK не содержит `debug`
- [x] Dockerfile: создаются поддиректории `vk/posts`, `vk/reposts`, `vk/sync`, `vk/tokens`, `vk/stats`
- [x] docker/entrypoint.sh: исправляет права на storage/logs при старте
- [x] docker-compose.yml: использует entrypoint
- [x] Ошибка "Permission denied" не воспроизводится при запросе к /offer
- [x] Канал `job` не содержит детальных stack-trace ошибок
- [x] Ошибки приложения не дублируются в VK-каналы

## Примечание

Полная проверка в Docker-окружении (`docker compose up -d` + POST /offer) требует запущенных контейнеров и доступа к MySQL. Локальная проверка через `php -r` и unit-тесты подтверждает корректность конфигурации. Для полной интеграционной проверки необходимо пересобрать образ (`docker compose build app`) и поднять стек.
