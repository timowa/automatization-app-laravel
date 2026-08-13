# Модуль публикаций и задач

## Назначение

Создание публикации по офферу и сценарию, управление задачами публикации, отслеживание зависимостей между задачами.

## Publication

**Файл:** `app/Models/Publication.php`

Таблица: `publications`.

Публикация — набор действий для оффера по выбранному сценарию.

Fillable: `offer_id`, `scenario`.

Cast: `scenario` → `ScenarioType` enum.

Связи:
- `tasks()` — HasMany `PublicationTask`.
- `offer()` — BelongsTo `Offer`.

## PublicationTask

**Файл:** `app/Models/PublicationTask.php`

Таблица: `publication_tasks`.

Задача — конкретное действие в рамках публикации (создать пост, репост, комментарий и т.д.).

Fillable: `publication_id`, `type`, `status`, `error`, `external_id`, `dependent_task_id`.

Cast: `type` → `PublicationTaskType`, `status` → `PublicationTaskStatus`.

Связи:
- `publication()` — BelongsTo `Publication`.
- `offer()` — HasOneThrough `Offer` через `Publication`.
- `parentTask()` — BelongsTo `PublicationTask` (через `dependent_task_id` — задача, от которой зависит текущая).

### Статусы задач (PublicationTaskStatus)

**Файл:** `app/Enums/PublicationTaskStatus.php`

- `WAITING` — задача ожидает, пока родительская задача завершится.
- `PENDING` — задача готова к выполнению, ждёт диспатча.
- `PROCESSING` — задача выполняется.
- `SUCCESS` — задача успешно выполнена.
- `FAILED` — задача завершилась ошибкой.

### Типы задач (PublicationTaskType)

**Файл:** `app/Enums/PublicationTaskType.php`

- `VK_POST` — создать пост на стене.
- `VK_COMMENT` — создать комментарий к посту.
- `VK_STORY` — создать историю.
- `VK_LOOP_STORY` — создать loop-историю (повторяющаяся раз в 3 дня).
- `VK_END_LOOP_STORY` — остановить loop-историю.
- `VK_REPOST` — сделать репост в группы.
- `VK_CREATE_PRODUCT` — создать товар в группе.
- `VK_EDIT_PRODUCT` — отредактировать товар.
- `VK_ARCHIVE_PRODUCT` — архивировать товар.

## Зависимости задач

### PublicationTaskDependenceInspector

**Файл:** `app/Helpers/PublicationTaskDependenceInspector.php`

Определяет, от какой задачи зависит данная.

- `VK_STORY`, `VK_LOOP_STORY`, `VK_COMMENT`, `VK_REPOST`, `VK_CREATE_PRODUCT` → зависят от `VK_POST`.
- `VK_EDIT_PRODUCT`, `VK_ARCHIVE_PRODUCT` → зависят от `VK_CREATE_PRODUCT`.
- Остальные (null) — независимые.

### PublicationTaskDependencyResolver

**Файл:** `app/Helpers/PublicationTaskDependencyResolver.php`

Освобождает зависимые задачи после завершения родительской.

- `release(int $completedTaskId)` — переводит все задачи с `dependent_task_id = $completedTaskId` и `status = WAITING` в статус `PENDING`.

Вызывается Job'ами после успешного завершения задачи.

## Жизненный цикл задач

```
Создание публикации (CreatePublicationAction)
    ↓
Независимые задачи → статус PENDING
Зависимые задачи → статус WAITING
    ↓
TaskDispatcher::dispatch() → Job::dispatch() для PENDING задач
    ↓
Job: PENDING → PROCESSING → SUCCESS
    ↓
PublicationTaskDependencyResolver::release() → WAITING → PENDING
    ↓
TaskDispatcher::dispatch() → Job::dispatch() для новых PENDING задач
    ↓
... (каскад)
```

## CreatePublicationAction

**Файл:** `app/Actions/CreatePublicationAction.php`

Создаёт публикацию и задачи в одной транзакции БД.

Поток:
1. `DB::transaction()`:
   - Создаёт `Publication` (`offer_id`, `scenario`).
   - Для каждого типа задачи из `Scenario::tasks()`:
     - Через `PublicationTaskDependenceInspector::inspect()` определяет зависимость.
     - Если зависимость null — создаёт задачу в статусе `PENDING`.
     - Если есть зависимость — создаёт задачу в статусе `WAITING` с `dependent_task_id` = ID родительской задачи.
   - Сохраняет созданные задачи в массив `$tasks` (ключ — тип задачи) для разрешения ссылок.
2. После транзакции вызывается `TaskDispatcher::dispatch()` для запуска независимых задач.

> Важно: ссылки на родительские задачи разрешаются внутри транзакции по массиву `$tasks`. Порядок задач в `Scenario::tasks()` должен гарантировать, что родительская задача создаётся раньше зависимой.

## Идемпотентность

- Дубли офферов отсеиваются на уровне `ReceiveOfferWebhookAction`.
- Публикация создаётся один раз для каждого нового оффера (через event-listener flow).
- Job'ы имеют `$tries = 1` — при ошибке не повторяются автоматически.