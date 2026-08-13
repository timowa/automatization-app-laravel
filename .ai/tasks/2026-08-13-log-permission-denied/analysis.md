# Analysis — Permission denied при записи логов

## Task overview

Исправить ошибку "Failed to open stream: Permission denied" при записи логов в Docker-окружении. Ошибка воспроизводится при обработке вебхука POST /offer — ReceiveOfferWebhookAction пытается написать в канал job, но www-data не имеет прав на файл job-2026-08-13.log.

## Current architecture

### Docker-окружение

Контейнер vk19-app:
- Образ: vk19-app-app, создан 2026-08-05T13:38:04Z
- Контейнер: создан 2026-08-05T13:38:23Z, запущен 2026-08-13T12:09:44Z
- CMD в образе: ["php-fpm"] (из базового php:8.4-fpm)
- Entrypoint в образе: ["docker-php-entrypoint"] (стандартный PHP-образ)
- PHP-FPM: master process работает от root, worker processes от www-data (UID 33)
- Volume: ./:/var/www/html — весь проект монтируется с Windows-хоста
- Дополнительный volume: ./storage/logs:/var/www/html/storage/logs (избыточный — уже покрывается общим)

### Файловые права в контейнере

storage/logs/ смонтирована с Windows-хоста (NTFS). Docker на Windows монтирует через VirtioFS или类似的机制.

Текущее состояние файлов в контейнере:
```
-rw-r--r-- 1 root     root     22934 Aug 12 23:11 laravel-2026-08-13.log
-rw-r--r-- 1 root     root     22934 Aug 12 23:11 check_tokens-2026-08-13.log
-rw-r--r-- 1 root     root     22934 Aug 12 23:11 stats-2026-08-13.log
-rw-r--r-- 1 www-data www-data 26497 Aug 13 12:17 job-2026-08-13.log  (после ручной починки)
```

Парадокс: www-data пишет в файл, созданный www-data (644), но не может писать в файл, созданный root (644). Файлы, созданные root, имеют owner=root, group=root, права 644 — www-data не владелец, не в группе, other=r-- (только чтение).

### Кто создаёт файлы от root?

Файлы логов создаются двумя путями:
1. PHP-FPM worker (www-data) — при Log::channel('job')->info(...) — создаёт файл от www-data
2. Artisan-команды (root) — при запуске `docker exec vk19-app php artisan ...` — создаёт файл от root

Когда файл впервые создаётся через `docker exec` (root), он получает owner=root, group=root, права 644. После этого PHP-FPM worker (www-data) не может в него писать.

### Что сделал предыдущий backend-агент

Изменения в файлах проекта:
1. config/logging.php — VK-каналы перенесены в storage/logs/vk/<channel>/error.log
2. .env — LOG_CHANNEL=single, LOG_STACK=single (убран debug и дублирование)
3. Dockerfile — добавлен COPY docker/entrypoint.sh, CMD заменён на ["/usr/local/bin/entrypoint.sh"]
4. docker/entrypoint.sh — chown -R www-data:www-data + chmod -R 775 при старте
5. docker-compose.yml — command: /usr/local/bin/entrypoint.sh

### Почему не сработало

**Причина 1: Образ не пересобран.**
Образ vk19-app-app создан 2026-08-05. Изменения в Dockerfile (COPY entrypoint.sh, CMD) сделаны 2026-08-13, но `docker compose build` не выполнялся. В контейнере:
- /usr/local/bin/entrypoint.sh — отсутствует (No such file or directory)
- CMD контейнера — ["php-fpm"] (старый, из базового образа)
- Entrypoint — docker-php-entrypoint (стандартный)

Контейнер стартует через docker-php-entrypoint → php-fpm, минуя entrypoint.sh. Права не исправляются.

**Причина 2: chown/chmod не работают на NTFS-монтировании.**
Даже после пересборки образа, entrypoint.sh выполняет:
```bash
chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true
chmod -R 775 /var/www/html/storage/logs 2>/dev/null || true
```

На Windows-хосте (Docker Desktop с WSL2) тома монтируются через NTFS/9p/VirtioFS. Команды chown/chmod выполняются без ошибки (exit code 0), но не меняют фактические права — права определяются конфигурацией монтирования Docker, а не Unix-командами. Флаги `2>/dev/null || true` маскируют любые ошибки, поэтому проблема будет скрыта.

Доказательство: внутри контейнера `chown www-data:www-data job-2026-08-13.log` и `chmod 664` выполнились успешно (вывели новый owner/права), но это потому что пользователь root выполняет их на смонтированном томе. Проблема в том, что новые файлы, созданные root через docker exec, всё равно будут root:root 644, и последующий chown через entrypoint на NTFS может не сработать.

### Проверка конфигурации

Конфигурация logging.php применена корректно (через volume-монтирование):
- default channel: single
- job path: /var/www/html/storage/logs/job.log (driver: daily → job-YYYY-MM-DD.log)
- job level: info
- single path: /var/www/html/storage/logs/laravel.log (driver: single → laravel.log)

Драйвер daily для канала job создаёт файл `job-YYYY-MM-DD.log`. Дата определяется по timezone приложения (Asia/Krasnoyarsk, UTC+7).

VK-поддиректории созданы частично:
- storage/logs/vk/posts/ — существует, есть error-2026-08-13.log
- storage/logs/vk/reposts/ — НЕ существует
- storage/logs/vk/sync/ — НЕ существует
- storage/logs/vk/tokens/ — НЕ существует
- storage/logs/vk/stats/ — НЕ существует

## Affected areas

- Dockerfile — пересборка образа
- docker/entrypoint.sh — нужен другой подход к правам
- docker-compose.yml — возможны изменения
- storage/logs/ — очистка старых файлов, созданных root
- config/logging.php — без изменений (конфигурация корректна)

## Existing implementation

### Текущий Dockerfile (после изменений предыдущей задачи)

```dockerfile
# Подготовка директорий для VK-логов
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

Проблема: mkdir в Dockerfile создаёт директории внутри образа, но volume-монтирование заменяет /var/www/html/storage/logs содержимым хоста — директории исчезают.

### Текущий docker/entrypoint.sh

```bash
#!/bin/sh
set -e
chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true
chmod -R 775 /var/www/html/storage/logs 2>/dev/null || true
mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats 2>/dev/null || true
exec php-fpm
```

Проблема: chown/chmod не работают на NTFS, `2>/dev/null || true` маскирует ошибки.

### Текущий docker-compose.yml

```yaml
services:
  app:
    volumes:
      - ./:/var/www/html
      - ./storage/logs:/var/www/html/storage/logs  # избыточно
```

Проблема: двойное монтирование storage/logs — второе правило монтирования перекрывает первое, но не даёт преимуществ.

## Architecture decisions

### Решение 1: Запуск php-fpm от www-data

Вместо попытки исправить права на файлах через chown/chmod (что не работает на NTFS), нужно запускать PHP-FPM от пользователя www-data. Тогда все файлы, создаваемые PHP-FPM, будут принадлежать www-data.

Варианты:

**Вариант A: `user: "33:33"` в docker-compose.yml**

```yaml
services:
  app:
    user: "33:33"  # www-data
```

PHP-FPM master process стартует от www-data. Но php-fpm по умолчанию пытается создать socket в /var/run/php, для чего нужны права root. Нужно либо менять путь socket, либо использовать TCP-подключение к nginx.

Проверка: nginx подключается к php-fpm через TCP (9000 порт) или через socket? Нужно проверить docker/nginx/default.conf.

**Вариант B: Изменить listen в php-fpm конфиге и запустить от www-data**

Если nginx подключается через TCP (9000), то php-fpm можно запускать от www-data без проблем — TCP-сокет не требует root.

Если через unix-socket, нужно либо переключиться на TCP, либо изменить права на socket.

**Вариант C: Оставить master от root, но обеспечить создание файлов от www-data**

PHP-FPM master запускается от root, workers — от www-data (текущая схема). Файлы логов создаются worker'ами от www-data. Проблема только в файлах, созданных через `docker exec` (root). Решение: не запускать artisan-команды через docker exec от root, а использовать `docker exec -u www-data`.

### Решение 2 (рекомендуемое): Комбинированный подход

1. **Удалить избыточное монтирование** `./storage/logs:/var/www/html/storage/logs` из docker-compose.yml — оно уже покрывается `./:/var/www/html`.

2. **entrypoint.sh** — убрать chown/chmod (не работают на NTFS), оставить только mkdir для VK-поддиректорий. Запуск php-fpm от root (master) → workers от www-data.

3. **Очистить файлы логов**, созданные от root — удалить старые файлы или сменить владельца с хоста (Windows-команда, не через Docker).

4. **Artisan-команды** запускать через `docker exec -u www-data` или через `docker compose exec -u www-data app php artisan ...`.

5. **Пересобрать образ** — `docker compose build app` после изменений в Dockerfile.

### Решение 3: Альтернатива — именованный volume для логов

Вместо монтирования storage/logs с хоста, использовать именованный Docker-volume:

```yaml
volumes:
  - ./:/var/www/html
  - logs-data:/var/www/html/storage/logs
```

Плюсы:
- Права полностью контролируются Docker (ext4 внутри volume), chown/chmod работают
- entrypoint.sh с chown/chmod будет работать
- Файлы, созданные root, можно исправить chown

Минусы:
- Логи не видны на хосте напрямую (нужен docker exec для просмотра)
- Или нужен отдельный механизм экспорта

### Финальное решение

Рекомендуется комбинированный подход (Решение 2) с обязательной пересборкой образа:

1. Убрать избыточное монтирование storage/logs из docker-compose.yml
2. Упростить entrypoint.sh — убрать chown/chmod, оставить mkdir
3. Очистить старые log-файлы с неправильными правами
4. Пересобрать образ и перезапустить контейнеры
5. Убедиться, что artisan-команды выполняются от www-data

Если nginx использует unix-socket для подключения к php-fpm — рассмотреть запуск php-fpm от www-data через `user: "33:33"` с корректировкой конфигурации socket.

## Dependencies

```
1. Проверить docker/nginx/default.conf — TCP или unix-socket
2. Очистка storage/logs/ — удаление файлов с root-правами
3. Пересборка образа — docker compose build app
4. Перезапуск — docker compose up -d
5. Проверка — POST /offer, проверка логов
```

Execution order: последовательно, один backend-агент.

## Risks

1. **Удаление логов** — при очистке storage/logs/ можно удалить нужные логи. Решение: удалить только файлы с owner=root, или все *.log файлы (логи — не критичные данные).

2. **Пересборка образа** — может занять время (установка пакетов). Решение: пересобрать только app-сервис.

3. **Artisan-команды от root** — если в проекте есть cron/scheduler, запускающий artisan от root, новые логи снова будут root-owned. Решение: запускать scheduler от www-data или через PHP-FPM.

4. **Unix-socket vs TCP** — если nginx подключается к php-fpm через unix-socket, запуск php-fpm от www-data потребует изменения прав на socket. Нужно проверить docker/nginx/default.conf.

5. **Двойное монтирование** — удаление `./storage/logs:/var/www/html/storage/logs` из docker-compose.yml не должно сломать ничего, так как `./:/var/www/html` уже покрывает этот путь.

## Questions

Нет. Информации достаточно.

## Definition of Done

- [ ] Образ vk19-app-app пересобран (дата создания > 2026-08-13)
- [ ] entrypoint.sh присутствует в контейнере (/usr/local/bin/entrypoint.sh)
- [ ] VK-поддиректории создаются при старте контейнера (posts, reposts, sync, tokens, stats)
- [ ] Файлы логов в storage/logs/ принадлежат www-data (или доступны для записи www-data)
- [ ] POST /offer не вызывает "Permission denied" в laravel.log
- [ ] Log::channel('job')->info(...) успешно пишет в job-YYYY-MM-DD.log
- [ ] Artisan-команды не создают log-файлы от root
- [ ] Логи доступны для чтения с хоста (storage/logs/)