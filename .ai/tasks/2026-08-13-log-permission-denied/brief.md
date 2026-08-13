# Permission denied при записи логов — исправление после неудачного рефакторинга

## Описание задачи

Предыдущая задача (2026-08-13-logging-refactor) была направлена на исправление ошибки "Permission denied" при записи логов в Docker-окружении, которая возникает при обработке вебхука POST /offer. Задача отмечена выполненной, но проблема осталась.

Ошибка в laravel.log:
```
The stream or file "/var/www/html/storage/logs/job-2026-08-13.log" could not be opened in append mode: Failed to open stream: Permission denied
```

Ошибка возникает в ReceiveOfferWebhookAction.php:26 при вызове Log::channel('job')->info('new Offer Reque...').

## Контекст

Предыдущая задача внесла изменения в:
- config/logging.php — иерархия VK-логов
- .env — LOG_CHANNEL=single, LOG_STACK=single
- Dockerfile — добавлен COPY docker/entrypoint.sh и CMD
- docker/entrypoint.sh — новый файл, chown/chmod при старте
- docker-compose.yml — command: /usr/local/bin/entrypoint.sh

Однако эти изменения не вступили в силу:
1. Docker-образ не пересобран (образ от 2026-08-05, изменения от 2026-08-13)
2. Контейнер запущен из старого образа — entrypoint.sh отсутствует в контейнере
3. Файлы логов принадлежат root:root с правами 644, www-data не может писать

Дополнительная проблема: даже после пересборки, подход с chown/chmod в entrypoint.sh не сработает на Windows-хосте, так как директория storage/logs монтируется из NTFS. На NTFS chown/chmod выполняются, но не имеют эффекта — права определяются точкой монтирования Docker.

## Требования

1. Обеспечить запись логов www-data в storage/logs/ без ошибки Permission denied.
2. Решение должно работать на Windows-хосте (NTFS-монтирование).
3. Решение должно быть устойчивым: работать при первом запуске и при пересборке.
4. Иерархия VK-логов (storage/logs/vk/<channel>/error-YYYY-MM-DD.log) должна создаваться автоматически.