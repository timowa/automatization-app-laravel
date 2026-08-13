# Review — Рефакторинг системы логирования

## Что проверить

1. Иерархия логов: `storage/logs/vk/<channel>/error-YYYY-MM-DD.log` — файлы создаются в поддиректориях, не в корне.
2. Разделение: ошибки приложения только в `laravel.log`; успех/неудача в `job`; детальные ошибки в VK-каналах.
3. Уровни логирования: VK-каналы — warning+, `job` — info+.
4. Права в Docker: `www-data` может писать во все поддиректории `storage/logs/`.
5. Permission denied не воспроизводится.
6. Нет несуществующего `debug` канала в конфигурации.

## Какие файлы проверить

- `config/logging.php` — пути, levels, driver.
- `.env` — LOG_CHANNEL, LOG_STACK, LOG_LEVEL.
- `Dockerfile` — mkdir, entrypoint, CMD.
- `docker/entrypoint.sh` — chown, chmod, mkdir, exec php-fpm.
- `docker-compose.yml` — volume mounts, command (если переопределён).

## Ожидаемое поведение

### Запрос к /offer (успешный)

```
storage/logs/
├── laravel.log                        — пустой (нет необработанных исключений)
├── laravel-2026-08-13.log              — пустой
├── job-2026-08-13.log                  — "new Offer Request" (info), "Offer created" (info)
├── vk/
│   ├── posts/
│   │   └── error-2026-08-13.log         — пустой (нет VK-ошибок)
│   ├── reposts/
│   │   └── error-2026-08-13.log         — пустой
│   ├── sync/
│   │   └── error-2026-08-13.log         — пустой
│   ├── tokens/
│   │   └── error-2026-08-13.log         — пустой
│   └── stats/
│       └── error-2026-08-13.log         — пустой
```

### Запрос к /offer (ошибка VK API при выполнении Job)

```
job-2026-08-13.log      — "Ошибка создания поста по офферу" (warning, без trace)
vk/posts/error-2026-08-13.log — полная ошибка VKApiException с trace (error)
laravel.log             — пустой (это не необработанное исключение)
```

### Необработанное исключение (например, SQL-ошибка миграции)

```
laravel.log             — полная ошибка с trace
laravel-2026-08-13.log  — полная ошибка с trace
job-2026-08-13.log      — НЕТ записи (не дублируется)
vk/posts/error-*.log    — НЕТ записи (не дублируется)
```

## Потенциальные риски

1. **Драйвер daily не создаёт поддиректории** — если Monolog не вызывает `mkdir` для несуществующих путей, файлы не создадутся. Проверить, что `storage/logs/vk/posts/` существует после первого запроса. Если нет — добавить `mkdir -p` в entrypoint (уже добавлено, но Monolog тоже должен).

2. **Windows-хост и Unix-права** — `chown`/`chmod` в entrypoint.sh могут не сработать на NTFS-томе. На Docker Desktop для Windows — работает через WSL2. На нативном Windows Docker — может не сработать. `2>/dev/null || true` предотвращает падение, но права могут остаться неправильными. В этом случае — запускать контейнер от `user: "33:33"` в docker-compose.

3. **Старые лог-файлы** — в `storage/logs/` останутся старые `vk-2026-08-13.log` и т.д. Они не мешают, но могут запутать. Не удалять автоматически.

4. **phpunit.xml** — если в testing-окружении `LOG_CHANNEL=stack` и `LOG_STACK` включает все каналы, тесты будут дублировать логи. Проверить `phpunit.xml` и при необходимости переопределить `LOG_CHANNEL` в testing.

5. **Уровень info для job** — `level: info` пропускает info, warning, error. Если в коде есть `Log::channel('job')->error(...)` (есть в ReceiveOfferWebhookAction при Throwable), эти записи попадут в job-лог. Это нормально — error-уровень job-лога означает "задача упала, но без trace".

## Сценарии тестирования

1. **Успешный вебхук**: отправить валидный оффер → проверить, что `job` содержит info-записи, `vk/posts/error-*` пуст, `laravel.log` пуст.

2. **Вебхук с неизвестным агентом**: отправить оффер с телефоном, которого нет в БД → `job` содержит warning "Не найден агент", `laravel.log` пуст, VK-каналы пусты.

3. **Необработанное исключение**: вызвать ошибку (например, остановить MySQL, отправить вебхук) → `laravel.log` содержит SQL-ошибку, `job` НЕ содержит, VK-каналы НЕ содержат.

4. **Ошибка VK API**: выполнить Job с невалидным токеном → `job` содержит warning "Ошибка создания поста", `vk/posts/error-*` содержит детальную ошибку VKApiException.

5. **Права в Docker**: после `docker compose up -d` проверить `ls -la storage/logs/vk/posts/` — владелец `www-data`, права `775`.

6. **Ротация**: проверить, что файлы именуются `error-2026-08-13.log` (с датой), а не `error.log`.