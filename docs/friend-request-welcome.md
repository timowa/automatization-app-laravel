# Автоприветствие на заявку в друзья

## Назначение

Для агента с включённой настройкой система периодически забирает входящие заявки в друзья из ВКонтакте и по одной отправляет приветственное личное сообщение (текст + опционально визитка). Заявку в друзья приложение **не принимает** (`friends.add` не вызывается).

## Поток

```
Расписание (4 раза в сутки)
    ↓
vk:sync-friend-requests
    ↓
SyncAgentFriendRequestsJob (по агентам с включённой настройкой)
    ↓
friends.getRequests → upsert в agent_vk_friend_requests
    ↓
Расписание (каждые 5 минут)
    ↓
vk:process-friend-request-welcome
    ↓
одна запись с message_id IS NULL и welcome_permanently_failed = false → ProcessFriendRequestWelcomeJob
    ↓
messages.send (текст + attachment photo{vk_user_id}_{media_id})
    ↓
сохранение message_id в agent_vk_friend_requests
```

## Настройки агента (UI)

В offcanvas настроек агента (`agents-list`):

| Поле | Хранение | Описание |
|---|---|---|
| Включить автоприветствие | `settings.friend_request_welcome_enabled` | Флаг |
| Текст письма | `settings.friend_request_welcome_text` | Если пусто — дефолт из `FriendRequestWelcomeText` |
| Визитка | `settings.friend_request_card_id` → `agent_welcome_cards` | Превью + загрузка JPEG/PNG |

Маршруты:

- `POST /agents/settings/{id}` — `AgentController::updateSettings` (`UpdateAgentSettingsRequest`).
- `POST /agents/{id}/welcome-card` — `AgentController::uploadWelcomeCard` (`UploadAgentWelcomeCardRequest`).

В таблице агентов столбцы `welcome` / `welcome-card` показывают, включено ли приветствие и есть ли визитка с `media_id > 0`.

## Визитка

**Action:** `app/Actions/Vk/StoreAgentWelcomeCardAction.php`

1. Сохраняет оригинал и thumb (Intervention Image, ширина ≤ 800) во временные пути на диске `public`.
2. Загружает оригинал во ВК: `photos.getMessagesUploadServer` → upload → `photos.saveMessagesPhoto`.
3. В БД пишет `agent_welcome_cards` (`path`, `thumb_path`, `media_id`) и ставит `settings.friend_request_card_id`.
4. Итоговые имена файлов: `agent-cards/agent_{id}_card.*` и `agent-cards/agent_{id}_card_thumb.*`.

В личном сообщении вложение формируется как `photo{owner_id}_{media_id}`, где `owner_id` — `vk_users.vk_user_id` агента. Сам токен и полный attachment string в открытый UI не выводятся.

Лимит загрузки PHP поднят в Docker (`docker/php/uploads.ini`, до 10M).

## Модели и таблицы

### settings (доп. поля)

- `friend_request_welcome_enabled` (bool)
- `friend_request_welcome_text` (text, nullable)
- `friend_request_card_id` (FK → `agent_welcome_cards.id`, nullable)

Связь: `Setting::welcomeCard()`, `Setting::hasWelcomeCard()`.

### agent_welcome_cards

Модель: `App\Models\AgentWelcomeCard`

| Поле | Смысл |
|---|---|
| `agent_id` | Агент |
| `path` | Оригинал на диске `public` |
| `thumb_path` | Превью для админки |
| `media_id` | ID фото во ВК (для attachment) |

Методы: `previewUrl()`, `originalUrl()`.

### agent_vk_friend_requests

Модель: `App\Models\AgentVkFriendRequest`

Уникальность: пара `(agent_id, user_id)`.

| Поле | Смысл |
|---|---|
| `user_id` | VK ID заявителя |
| `first_name`, `last_name`, … | Снимок из `friends.getRequests` |
| `payload` | Сырой фрагмент ответа |
| `message_id` | `NULL` — ещё не обработано; иначе ID сообщения во ВК |
| `welcome_permanently_failed` | `true` — ВКонтакте постоянно отказал (приватность, нет права писать, пользователь недоступен). Такая заявка больше не выбирается. Временный сбой (лимит, обрыв связи) флаг не ставит |

Константа `AGENT_ALREADY_MESSAGED_SENTINEL = 1` — зарезервированный sentinel (проверка «уже писал» через `messages.getHistory` в коде есть, но может быть отключена комментарием).

## Команды и расписание

| Команда | Расписание | Назначение |
|---|---|---|
| `vk:sync-friend-requests` | `0 0,6,12,18 * * *` | Диспатч sync-job по агентам с включённым флагом |
| `vk:process-friend-request-welcome` | `everyFiveMinutes()` | Берёт самую старую заявку с `message_id IS NULL` и `welcome_permanently_failed = false` (`lockForUpdate`) и ставит welcome-job |

Файлы: `app/Console/Commands/SyncFriendRequestsCommand.php`, `ProcessFriendRequestWelcomeCommand.php`; регистрация в `routes/console.php`.

## Jobs

### SyncAgentFriendRequestsJob

**Файл:** `app/Jobs/SyncAgentFriendRequestsJob.php`

- Проверяет флаг настройки и токен.
- `VkApiService::getFriendRequests()`.
- Upsert заявок в `agent_vk_friend_requests` (новые строки с `message_id = null`).
- Логи: канал `job` (info/warning), детали ошибок VK — канал `vk`.

### ProcessFriendRequestWelcomeJob

**Файл:** `app/Jobs/ProcessFriendRequestWelcomeJob.php`

- `ShouldBeUnique` по `friend_request_id` (`uniqueFor = 240`).
- Пропуски: уже есть `message_id`, выключена настройка, нет токена.
- Текст: `FriendRequestWelcomeText::resolve()`.
- Attachment: `FriendRequestWelcomeText::photoAttachment(ownerId, mediaId)` при наличии визитки.
- `sendDirectMessage` → атомарный `UPDATE … WHERE message_id IS NULL`.
- Постоянный отказ `VkApiErrorInspector::isPermanentDirectMessageRefusal()` ставит `welcome_permanently_failed = true` и не пишет `message_id`. Временный сбой оставляет заявку в очереди.
- Rate limit: `usleep(350_000)` перед отправкой.

## VkApiService (новые методы)

| Метод | VK API |
|---|---|
| `getFriendRequests()` | `friends.getRequests` (extended) |
| `hasOutgoingMessageWithUser(int $userId)` | `messages.getHistory` |
| `uploadMessagesPhoto(string $absolutePath)` | upload server + `photos.saveMessagesPhoto`, возвращает `media_id` |
| `sendDirectMessage(int $userId, string $message, ?string $attachment)` | `messages.send` |

Аналоги есть в `FakeVkApiService` для тестов.

## Хелпер

**Файл:** `app/Helpers/FriendRequestWelcomeText.php`

- Дефолтный текст приветствия.
- Подстановка имени агента.
- Сборка строки attachment `photo{owner}_{media}`.

## Сохранение столбцов таблиц (ColVis)

В `agents-list` и `agent-friends` видимость столбцов пишется в `localStorage` по стабильным ключам `data-col`.

Важно: DataTables 2 убирает скрытые `<th>` из DOM `tHead.cells`. Ключи столбцов нужно брать только через API:

```js
dt.columns().header().toArray().map(th => th.getAttribute('data-col'))
```

Ключи хранилища: `dt:agents-list:v3`, `dt:agent-friends:{id}:v3` (старые `v1`/`v2` удаляются при загрузке страницы).

## Тесты

- `tests/Feature/FriendRequestWelcomeTest.php` — настройки, загрузка визитки, сценарии welcome.
- `tests/Feature/AgentSettingsTest.php` — обновлены под новые поля настроек.

## Миграции

- `2026_10_21_000001_add_friend_request_welcome_to_settings_table.php`
- `2026_10_21_000002_create_agent_vk_friend_requests_table.php`
- `2026_10_21_000003_create_agent_welcome_cards_and_link_settings.php`
- `2026_10_24_000002_add_welcome_permanently_failed_to_agent_vk_friend_requests.php`
