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
4. Отправляет уведомление через `vkApi->sendTokensMessage()`.
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
   - Загружает через `vkApi->storiesPost()`.
   - Обновляет `last_published_at = now()`.
3. `usleep(350_000)` между итерациями.

Шаблон истории выбирается по типу сделки (SaleStoriesTemplate / RentStoriesTemplate).

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

Сбор статистики просмотров VK-постов.

Поток:
1. Загружает все `VkWallPost` с активными офферами (`is_active = true`).
2. Группирует посты по `owner_id` (VK-пользователю).
3. Для каждого пользователя: `vkApi->getPostsStats(postIds)` → извлекает `views.count`.
4. Массово вставляет записи в `VkPostStat` через `insert()`.
5. `sleep(1)` между пользователями (rate limit).
6. Логирует (канал `stats`).

### app:truncate

**Файл:** `app/Console/Commands/TruncateCommand.php`

Очистка таблиц `offers`, `vk_posts`, `jobs`. Снимает `FOREIGN_KEY_CHECKS` на время очистки.

Служебная команда для разработки/тестирования.