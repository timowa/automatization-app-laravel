# Backend Task — Permission denied при записи логов

## Цель

Исправить ошибку "Failed to open stream: Permission denied" при записи логов в Docker-окружении. Обеспечить устойчивую запись логов от www-data в storage/logs/ на Windows-хосте (NTFS-монтирование).

## Контекст

Проект — Laravel 13.24 + PHP 8.4 + Docker (docker-compose). Контейнер app (vk19-app) работает на базе php:8.4-fpm. Nginx подключается к php-fpm через TCP (app:9000), не через unix-socket.

Платформа хоста: Windows 10, Docker Desktop с WSL2. Том `./:/var/www/html` монтируется через NTFS/VirtioFS.

PHP-FPM: master process от root, workers от www-data (UID 33). Файлы логов создаются workers от www-data, НО файлы, созданные через `docker exec` (от root), получают owner=root, права 644 — www-data не может в них писать.

Предыдущая задача (2026-08-13-logging-refactor) изменила Dockerfile, docker/entrypoint.sh, docker-compose.yml, config/logging.php, .env, но:
- Образ не пересобран (образ от 2026-08-05, изменения от 2026-08-13)
- entrypoint.sh отсутствует в контейнере
- chown/chmod в entrypoint.sh не работают на NTFS-монтировании

## Существующая реализация

### docker/nginx/default.conf

Nginx подключается к php-fpm через TCP:
```
fastcgi_pass app:9000;
```

### Dockerfile (текущий, после изменений предыдущей задачи)

```dockerfile
FROM php:8.4-fpm
# ... установки пакетов ...

WORKDIR /var/www/html

# Подготовка директорий для VK-логов
RUN mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats

# Entrypoint для исправления прав при старте контейнера
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

CMD ["/usr/local/bin/entrypoint.sh"]
```

Проблема: mkdir в Dockerfile создаёт директории внутри образа, но volume `./:/var/www/html` заменяет всё содержимое — директории исчезают при старте.

### docker/entrypoint.sh (текущий)

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

Проблема: chown/chmod не работают на NTFS. `2>/dev/null || true` маскирует ошибки.

### docker-compose.yml (текущий)

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: vk19-app
    restart: unless-stopped
    working_dir: /var/www/html
    command: /usr/local/bin/entrypoint.sh
    volumes:
      - ./:/var/www/html
      - ./storage/logs:/var/www/html/storage/logs
```

Проблема: `./storage/logs:/var/www/html/storage/logs` — избыточное монтирование, уже покрывается `./:/var/www/html`.

### config/logging.php — БЕЗ ИЗМЕНЕНИЙ

Конфигурация корректна и уже применена через volume-монтирование:
- default: single (laravel.log)
- job: daily, path=logs/job.log, level=info
- vk: daily, path=logs/vk/posts/error.log, level=warning
- vkRepost: daily, path=logs/vk/reposts/error.log, level=warning
- vk-sync: daily, path=logs/vk/sync/error.log, level=info
- check_tokens: daily, path=logs/vk/tokens/error.log, level=info
- stats: daily, path=logs/vk/stats/error.log, level=info

## Необходимые изменения

### 1. docker-compose.yml

Удалить избыточное монтирование `./storage/logs:/var/www/html/storage/logs`.

Удалить `command: /usr/local/bin/entrypoint.sh` — CMD из Dockerfile достаточен. Если оставить command в docker-compose, он переопределяет CMD из Dockerfile, что может конфликтовать при изменениях.

Итоговый сервис app:
```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: vk19-app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    networks:
      - vk19-network
    depends_on:
      mysql:
        condition: service_healthy
```

### 2. docker/entrypoint.sh

Убрать chown/chmod (не работают на NTFS). Оставить только создание поддиректорий и запуск php-fpm.

```bash
#!/bin/sh
set -e

# Создаём поддиректории для VK-логов (volume монтирует с хоста, директории из образа не сохраняются)
mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats

# Запускаем php-fpm (master от root, workers от www-data)
exec php-fpm
```

### 3. Dockerfile

Без изменений относительно текущего состояния. COPY entrypoint.sh и CMD остаются.

### 4. Очистка storage/logs/

Удалить все существующие log-файлы в storage/logs/ (включая поддиректории). Файлы созданы смесью root и www-data, содержат дублирующиеся записи от старой конфигурации LOG_STACK. Логи — не критичные данные, пересоздаются автоматически.

Конкретно удалить:
- storage/logs/*.log (все плоские log-файлы)
- storage/logs/vk/ (поддиректория с partial-данными)
- storage/logs/laravel.log (12 МБ файл с ошибками)

НЕ удалять: storage/logs/.gitignore

### 5. Пересборка образа и перезапуск

```bash
docker compose build app
docker compose up -d
```

### 6. Проверка

После перезапуска:
1. Проверить наличие entrypoint.sh в контейнере
2. Проверить создание VK-поддиректорий
3. Отправить тестовый вебхук POST /offer
4. Проверить отсутствие "Permission denied" в laravel.log
5. Проверить запись в job-YYYY-MM-DD.log

## Затрагиваемые файлы

- docker-compose.yml — удаление избыточного volume и command
- docker/entrypoint.sh — упрощение (убрать chown/chmod)
- storage/logs/*.log — удаление
- storage/logs/vk/ — удаление

## API изменения

Нет.

## Модели / Миграции

Нет.

## Тесты

Unit-тесты не затрагиваются (логирование не тестируется на уровне конфигурации Docker).

Проверка — интеграционная:
1. `docker compose build app` — успешная пересборка
2. `docker compose up -d` — контейнеры запущены
3. `docker exec vk19-app ls /usr/local/bin/entrypoint.sh` — файл существует
4. `docker exec vk19-app ls -la /var/www/html/storage/logs/vk/` — 5 поддиректорий
5. POST /offer с валидным API key — не 500, нет "Permission denied" в логах
6. `docker exec vk19-app cat /var/www/html/storage/logs/job-*.log` — содержит запись о запросе

## Ограничения

- НЕ менять config/logging.php — конфигурация корректна
- НЕ менять .env — значения корректны
- НЕ добавлять chown/chmod в entrypoint.sh — не работают на NTFS
- НЕ добавлять `user: "33:33"` в docker-compose.yml — php-fpm master должен работать от root для корректного старта (workers понижаются до www-data автоматически)
- НЕ использовать `docker exec` от root для artisan-команд, которые пишут логи — использовать `docker exec -u www-data` или `docker compose exec -u www-data app`

## Out of scope

- config/logging.php — без изменений
- .env — без изменений
- Код приложения — без изменений
- Именованные Docker-volumes для логов (альернативный подход, не используется)

## Definition of Done

- [ ] docker-compose.yml: удалено избыточное монтирование ./storage/logs и command
- [ ] docker/entrypoint.sh: убраны chown/chmod, оставлены mkdir + exec php-fpm
- [ ] storage/logs/: удалены старые log-файлы (сохранён .gitignore)
- [ ] Образ пересобран: docker compose build app (дата создания > 2026-08-13)
- [ ] Контейнеры перезапущены: docker compose up -d
- [ ] entrypoint.sh присутствует в контейнере: docker exec vk19-app ls /usr/local/bin/entrypoint.sh
- [ ] VK-поддиректории созданы: docker exec vk19-app ls /var/www/html/storage/logs/vk/ (5 директорий)
- [ ] POST /offer не вызывает ошибку "Permission denied"
- [ ] job-YYYY-MM-DD.log содержит запись о запросе, принадлежит www-data
- [ ] laravel.log не содержит новых записей "Permission denied"