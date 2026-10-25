# Модуль консольных команд

## Назначение

Artisan-команды для фоновых задач: проверка токенов, сбор статистики, переопубликация loop-историй, синхронизация профилей, очистка таблиц.

## Команды

### vk:check-tokens

**Файл:** `app/Console/Commands/CheckTokensCommand.php`

Проверка доступности VK-токенов всех агентов и уведомление о недоступных.

Поток:
1. Перебирает всех `VkUser`, проверяет токен через `vkApi->checkToken()`.
2. Обновляет `is_token_available` в БД (batch UPDATE через CASE).
3. Находит недоступных, формирует текст с именами и ссылками на профили.
4. Отправляет уведомление через `vkApi->sendTechMessage()`.
5. Логирует (канал `check_tokens`).

`usleep(300_000)` между проверками (rate limit).

### vk:publish-loop-stories

**Файл:** `app/Console/Commands/PublishLoopStoriesCommand.php`

Переопубликация активных loop-историй раз в 3 дня.

Поток:
1. Находит все `VkLoopStory` с `is_active = true` и `last_published_at < now()-3 дня` (или null).
2. Для каждой:
   - Находит пост и оффер.
   - Генерирует баннер через `VkStoriesGenerator`.
   - Загружает через `VkStoryPoster` (до 3 попыток на код 1, HTTP 405 и обрыв связи).
   - При успехе обновляет `last_published_at = now()`.
   - Если локального поста нет, `wall.getById` не подтвердил пост или проверка поста бросила ошибку, ставит `is_active = false` и не сдвигает `last_published_at`.
   - Если ссылка на фото недоступна (`StoryPublicationSkippedException` с текстом про недоступную ссылку или 404), ставит `is_active = false` и не сдвигает `last_published_at`.
   - Обрыв связи после исчерпания попыток не сдвигает `last_published_at`: цикл остаётся к следующему запуску.
3. `usleep(350_000)` между итерациями.

Шаблон истории выбирается по типу сделки (SaleStoriesTemplate / RentStoriesTemplate).

### vk:daily-report

**Файл:** `app/Console/Commands/DailyReportCommand.php`

Ежедневный отчёт о работе системы. Запускается по расписанию в 07:00.

Поток:
1. Считает офферы за последние 24 часа (`offers.created_at >= now()->subDay()`).
2. Считает опубликованные посты за 24 часа (`vk_posts.posted_at >= now()->subDay()`).
3. Считает ошибки в задачах за 24 часа (`publication_tasks.status = 'failed' AND updated_at >= now()->subDay()`).
4. Формирует текст отчёта и отправляет через `vkApi->sendTechMessage()`.
5. Логирует: info в канал `job` при успехе, warning в `job` + error в `vk` при ошибке.

### vk:notify-changelog

**Файл:** `app/Console/Commands/NotifyChangelogCommand.php`

Уведомление о последней версии продукта из `config/changelog.php`. Запускается при деплое на прод (GitHub Actions). Повторный деплой той же версии сообщение не отправляет.

Поток:
1. Берёт первый релиз через `ChangelogService::latest(1)`.
2. Сравнивает `version` с последней строкой `changelog_notifications` (`orderByDesc('id')`).
3. Если версия та же — завершается успешно без отправки. В канал `job` пишется info.
4. Формирует текст: версия, дата и список изменений.
5. Отправляет через `vkApi->sendTechMessage()` фиксированным получателям от имени сообщества.
6. После успешной отправки добавляет строку в `changelog_notifications` (`version`, `notified_at`). Если отправка упала, строка не пишется, следующий запуск повторит её.
7. Логирует: info в канал `job` при успехе, warning в `job` + error в `vk` при ошибке.
8. Если релизов нет — завершается успешно без отправки.

Таблица `changelog_notifications`: `id`, `version`, `notified_at`. Модели нет, чтение и запись через `DB`.

> ВАЖНО: миграция `2026_10_24_000001_create_changelog_notifications_table` сразу записывает `config('changelog.current')`, чтобы деплой этой таблицы не отправил уже объявленную версию повторно.

### vk:sync-users

**Файл:** `app/Console/Commands/SyncVkUsersCommand.php`

Массовая синхронизация VK-профилей агентов.

Поток:
1. Перебирает всех `VkUser`.
2. Вызывает `SyncVkUserAction::execute()` для каждого.
3. `usleep(333_333)` между итерациями (≈3 запроса/сек).
4. Логирует (канал `vk-sync`).

### vk:get-stats

**Файл:** `app/Console/Commands/GetStatsCommand.php`

Сбор статистики просмотров VK-постов. Запускается по расписанию раз в 2 часа.

Поток:
1. Получает список активных офферов (`offers.status = 1` — `OfferStatus::ACTIVE`) через `DB::table('offers')`.
2. Получает список агентов (`agent_id`), у которых есть активные офферы.
3. Получает все `VkWallPost`, связанные с активными офферами.
4. Группирует посты по `owner_id` (VK ID пользователя) и сопоставляет с `VkUser` через `agent_id`.
5. Для каждого агента проверяет доступность токена (`is_token_available` и непустой токен).
6. Формирует список ID постов в формате `{owner_id}_{post_id}` и разбивает на батчи по 100 (лимит VK API `wall.getById`).
7. Вызывает `vkApi->getPostsStats()` → извлекает `views.count`, `reposts.count`, `likes.count`, `comments.count` из ответа.
8. Массово вставляет записи в `VkPostStat` через `insert()`.
9. `sleep(1)` между агентами (rate limit: не более 3 запросов в секунду).
10. Логирует: warning/error в канал `stats`, info об успехе в канал `job`.

Ограничения:
- Токены никогда не логируются.
- При ошибке VK API по одному агенту обработка продолжается для остальных.
- Если активных офферов или постов нет — команда завершается успешно с соответствующей записью в лог.

### vk:sync-friend-requests

**Файл:** `app/Console/Commands/SyncFriendRequestsCommand.php`

Расписание: `0 0,6,12,18 * * *` (4 раза в сутки).

Для агентов с `friend_request_welcome_enabled` диспатчит `SyncAgentFriendRequestsJob` (со сдвигом delay, чтобы не упираться в rate limit).

### vk:process-friend-request-welcome

**Файл:** `app/Console/Commands/ProcessFriendRequestWelcomeCommand.php`

Расписание: каждые 5 минут.

Берёт до 25 самых старых записей `agent_vk_friend_requests` с `message_id IS NULL` и `welcome_permanently_failed = false` (`lockForUpdate`) и диспатчит один `ProcessFriendRequestWelcomeJob`. Постоянный отказ ВКонтакте помечает только эту заявку.

Подробнее: [friend-request-welcome.md](friend-request-welcome.md).

### vk:dispatch-birthday-wishes

**Файл:** `app/Console/Commands/DispatchBirthdayWishesCommand.php`

Расписание: каждый день в 04:00 по времени приложения.

Ставит `WishHappyBirthdayJob` друзьям с полной датой рождения сегодня, возрастом 18–70 и включённой настройкой пола. Уже поздравленные в этом году (`last_birthday_wish_at`) не попадают в очередь.

Постоянный отказ ВКонтакте (`Access denied: no access to call this method`, `Permission to perform this action is denied` и другие отказы личного сообщения) пишет в `agents_vk_friends`:

- `birthday_wish_denied_at` — дата отказа;
- `birthday_wish_denied_token_hash` — SHA-256 текущего токена, сам токен не сохраняется и не логируется.

Пока год отказа совпадает с текущим и хеш совпадает с токеном агента, задача снова не ставится. Смена токена или следующий год снова допускают попытку. В канал `job` пишется причина «у агента нет права на личные сообщения». Успешная отправка по-прежнему заполняет только `last_birthday_wish_at`.

Синхронизация друзей эти два поля не перезаписывает (`SyncAgentVkFriendsAction::UPDATE_COLUMNS`).

Миграция: `2026_10_24_000003_add_birthday_wish_denial_to_agents_vk_friends.php`.

### logs:query

**Файл:** `app/Console/Commands/QueryLogsCommand.php`

Выборка из JSONL-журналов: группы одинаковых ошибок, число повторов, время первого и последнего появления, короткие примеры. Если передан `--request`, в ответе есть связанные события этого запроса.

По умолчанию интервал — последние 24 часа. Каталог — `storage/logs`. Тот же контракт отдаёт MCP-инструмент `query_logs`.

Подробнее: [logging.md](logging.md).

### app:truncate

**Файл:** `app/Console/Commands/TruncateCommand.php`

Очистка таблиц `offers`, `vk_posts`, `jobs`. Снимает `FOREIGN_KEY_CHECKS` на время очистки.

Служебная команда для разработки/тестирования.