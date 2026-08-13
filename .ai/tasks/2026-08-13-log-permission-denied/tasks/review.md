# Review Task — Permission denied при записи логов

## Что проверить

1. **Пересборка образа** — образ vk19-app-app пересобран после изменений в Dockerfile/entrypoint.sh
2. **Права на логи** — www-data может писать во все log-файлы в storage/logs/
3. **Отсутствие Permission denied** — при обработке вебхука POST /offer не возникает ошибка записи логов
4. **VK-поддиректории** — создаются автоматически при старте контейнера
5. **Избыточное монтирование удалено** — storage/logs не монтируется дважды

## Какие файлы проверить

- docker-compose.yml — нет двойного монтирования storage/logs, нет command
- docker/entrypoint.sh — нет chown/chmod, есть mkdir + exec php-fpm
- storage/logs/ — нет старых файлов с root-правами
- storage/logs/.gitignore — сохранён

## Ожидаемое поведение

### При старте контейнера

1. entrypoint.sh выполняется (CMD из Dockerfile)
2. Создаются поддиректории: storage/logs/vk/{posts,reposts,sync,tokens,stats}
3. php-fpm запускается: master от root, workers от www-data

### При запросе POST /offer

1. ReceiveOfferWebhookAction вызывает Log::channel('job')->info(...)
2. Драйвер daily создаёт файл storage/logs/job-YYYY-MM-DD.log
3. Файл создаётся от www-data (PHP-FPM worker)
4. Запись успешно выполняется — нет "Permission denied"
5. laravel.log не содержит новых ошибок логирования

### При artisan-командах (запуск от www-data)

1. `docker compose exec -u www-data app php artisan ...`
2. Логи пишутся от www-data
3. Файлы принадлежат www-data, права 644

## Потенциальные риски

1. **Artisan от root** — если кто-то запустит `docker exec vk19-app php artisan ...` (без -u www-data), файлы логов будут созданы от root. PHP-FPM worker (www-data) не сможет писать в них. Решение: всегда использовать `-u www-data`.

2. **Очистка логов** — при удалении старых log-файлов можно случайно удалить .gitignore. Проверить, что .gitignore сохранён.

3. **Драйвер daily и дата** — дата в имени файла (job-YYYY-MM-DD.log) определяется по timezone приложения (Asia/Krasnoyarsk, UTC+7). Убедиться, что файл имеет правильную дату.

4. **MKDIR в entrypoint** — если storage/logs/ недоступен для записи при старте контейнера (маловероятно на NTFS-монтировании), mkdir не сработает. Проверить, что директория storage/logs/ доступна для записи (на Windows-хосте с Docker Desktop это обычно так).

5. **config:cache** — если был закеширован config, изменения logging.php могут не примениться. Проверить: `docker exec vk19-app php artisan config:clear` при необходимости.

## Сценарии тестирования

### Сценарий 1: Базовая запись лога

```bash
# Очистить логи
docker exec vk19-app rm -f /var/www/html/storage/logs/*.log
docker exec vk19-app rm -rf /var/www/html/storage/logs/vk/

# Перезапустить контейнер (entrypoint создаст директории)
docker compose restart app

# Проверить директории
docker exec vk19-app ls /var/www/html/storage/logs/vk/
# Ожидание: posts, reposts, sync, tokens, stats

# Отправить тестовый вебхук
curl --noproxy '*' -X POST http://localhost:8080/offer \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <API_KEY>" \
  -d '{"offers":[]}'

# Проверить логи
docker exec vk19-app cat /var/www/html/storage/logs/job-*.log
# Ожидание: содержит запись о запросе, нет ошибок

docker exec vk19-app grep "Permission denied" /var/www/html/storage/logs/laravel.log
# Ожидание: нет новых записей (или пустой файл)
```

### Сценарий 2: Права на файлы

```bash
# После запроса POST /offer
docker exec vk19-app stat -c '%U:%G %a %n' /var/www/html/storage/logs/job-*.log
# Ожидание: www-data:www-data 644 .../job-YYYY-MM-DD.log
```

### Сценарий 3: Artisan от www-data

```bash
docker compose exec -u www-data app php artisan tinker --execute="Log::channel('job')->info('test');"
docker exec vk19-app stat -c '%U:%G %a %n' /var/www/html/storage/logs/job-*.log
# Ожидание: www-data:www-data 644
```

### Сценарий 4: VK-каналы

```bash
docker compose exec -u www-data app php artisan tinker --execute="Log::channel('vk')->warning('test vk warning');"
docker exec vk19-app cat /var/www/html/storage/logs/vk/posts/error-*.log
# Ожидание: содержит "test vk warning"
```