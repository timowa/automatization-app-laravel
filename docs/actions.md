# Модуль Actions (слой действий)

## Назначение

Действия (Actions) — слой бизнес-логики, вынесенный из контроллеров и listener'ов. Каждый Action отвечает за одну операцию.

## Actions

### ReceiveOfferWebhookAction

**Файл:** `app/Actions/ReceiveOfferWebhookAction.php`

Обработка входящего вебхука с массивом офферов.

Зависимости: `OfferParser`, `OfferChangesDetector`.

Поток:
1. Логирует количество офферов.
2. Для каждого: парсит → проверяет дубли → находит предыдущий → создаёт Offer → диспатчит `OfferCreatedEvent`.
3. Ошибки парсинга логируются и пропускаются.

Подробнее: `docs/webhook-and-offer.md`.

### CreatePublicationAction

**Файл:** `app/Actions/CreatePublicationAction.php`

Создаёт публикацию и задачи в одной транзакции.

Зависимости: `PublicationTaskDependenceInspector`.

Поток:
1. `DB::transaction()`:
   - Создаёт `Publication` (`offer_id`, `scenario`).
   - Для каждой задачи из `Scenario::tasks()`:
     - Определяет зависимость через `PublicationTaskDependenceInspector`.
     - Независимые → `PENDING`. Зависимые → `WAITING` с `dependent_task_id`.
2. Сохраняет ссылки на созданные задачи в массив для разрешения зависимостей внутри транзакции.

Подробнее: `docs/publications-and-tasks.md`.

### SyncVkUserAction

**Файл:** `app/Actions/Vk/SyncVkUserAction.php`

Синхронизирует профиль VK-пользователя через VK API.

Зависимости: `VkApiService`.

Поток:
1. Проверяет существование VkUser и наличие токена.
2. Вызывает `users.get` с расширенными полями (first_name, last_name, screen_name, bdate, relation, city, и т.д.).
3. Заполняет модель VkUser из ответа через `fillVkUserFromResponse()`.
4. Сохраняет модель.
5. Логирует результат (канал `vk-sync`).

Используется:
- Консольной командой `vk:sync-users` (массовая синхронизация).
- `AgentController::save()` (после обновления агента).