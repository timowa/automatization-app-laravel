# Модуль Jobs (VK API задачи)

## Назначение

Асинхронное выполнение задач публикации через VK API. Каждый Job отвечает за один тип задачи.

## Общая структура Job

Все Jobs реализуют `ShouldQueue`, используют трейты `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`. `$tries = 1` — без авто-повторов.

Конструктор принимает `int $taskId` (ID `PublicationTask`), а также внедрённые зависимости: `PublicationTaskDependencyResolver`, `TaskDispatcher`.

Общий паттерн `handle()`:
1. `PublicationTask::findOrFail($taskId)`.
2. `task->update(['status' => PROCESSING])`.
3. Загрузка оффера, агента, VkUser через связи.
4. Проверка наличия токена.
5. `vkApi->setToken($token)`.
6. Выполнение VK API вызова.
7. При успехе:
   - `task->update(['status' => SUCCESS, 'external_id' => ...])`.
   - Лог info (канал `job`) — без деталей ошибки.
   - `taskDependencyResolver->release($taskId)` — освобождение зависимых задач.
   - `taskDispatcher->dispatch($publicationId)` — диспатч новых PENDING задач.
8. При ошибке:
   - `task->update(['status' => FAILED, 'error' => ...])`.
   - Лог warning (канал `job`) — без текста ошибки.
   - Лог error (канал `vk`) — с полным текстом ошибки и trace.

> ВАЖНО: в Job'ах никогда не используется `finally{}` — он перезаписал бы SUCCESS/FAILED статус.

## JobResolver

**Файл:** `app/Helpers/JobResolver.php`

Маппинг `PublicationTaskType` → класс Job:

| PublicationTaskType | Job |
|---|---|
| `VK_POST` | `CreateVkPostJob` |
| `VK_STORY` | `CreateVkStoriesJob` |
| `VK_REPOST` | `CreateVkRepostJob` |
| `VK_COMMENT` | `CreateVkCommentJob` |
| `VK_LOOP_STORY` | `CreateVkLoopStoryJob` |
| `VK_END_LOOP_STORY` | `EndVkLoopStoryJob` |
| `VK_CREATE_PRODUCT` | `CreateVkProductJob` |
| `VK_EDIT_PRODUCT` | `EditVkProductJob` |
| `VK_ARCHIVE_PRODUCT` | `ArchiveVkProductJob` |

## Jobs

### CreateVkPostJob

**Файл:** `app/Jobs/CreateVkPostJob.php`

Создаёт пост на стене агента во ВКонтакте.

Доп. зависимость: `ScenarioVkPostTemplateResolver` — определяет шаблон поста по типу сценария.

Поток:
1. Загружает оффер через `task->publication->offer`.
2. Получает агента и VkUser.
3. Создаёт `VkPostContext` через `VkPostContextFactory`.
4. Резолвит шаблон через `ScenarioVkPostTemplateResolver::resolve()`.
5. Генерирует текст через `VkPostGenerator::generate()`.
6. Вызывает `vkApi->wallPost(ownerId, message, images)`.
7. Создаёт запись `VkWallPost` (`offer_id`, `post_id`, `owner_id`, `task_id`).
8. Сохраняет `external_id` = ID записи VkWallPost.

### CreateVkRepostJob

**Файл:** `app/Jobs/CreateVkRepostJob.php`

Делает репост созданного поста в группы.

Поток:
1. Через `task->parentTask` находит родительскую задачу (VK_POST).
2. Через `external_id` родительской задачи находит `VkWallPost`.
3. Загружает все группы из `VkGroup::all()`.
4. Вызывает `vkApi->createReposts(userId, postId, groupIds)` — использует VKScript `execute()` для пакетного репоста (до 25 групп за один вызов).
5. Проверяет результат каждого репоста, логирует неудачи (канал `vkRepost`).

### CreateVkStoriesJob

**Файл:** `app/Jobs/CreateVkStoriesJob.php`

Создаёт историю (story) со ссылкой на созданный пост.

Поток:
1. Через `task->parentTask` находит `VkWallPost`.
2. Создаёт `VkStoriesContext` через `VkStoriesContextFactory`.
3. Резолвит шаблон истории по типу сделки (SaleStoriesTemplate / RentStoriesTemplate).
4. Генерирует баннер через `VkStoriesGenerator::generate()` → путь к файлу.
5. Вызывает `vkApi->storiesPost(postFullId, imagePath)`.
6. Проверяет `count >= 1` в ответе.
7. Удаляет временный файл баннера.

### CreateVkLoopStoryJob

**Файл:** `app/Jobs/CreateVkLoopStoryJob.php`

Создаёт loop-историю — историю, которая будет переопубликоваться раз в 3 дня.

Поток аналогичен `CreateVkStoriesJob`, но дополнительно:
- Создаёт/обновляет запись `VkLoopStory` (`offer_id`, `task_id`, `is_active = true`, `last_published_at = now()`).

Переопубликация выполняется консольной командой `vk:publish-loop-stories` (см. `docs/console-commands.md`).

### EndVkLoopStoryJob

**Файл:** `app/Jobs/EndVkLoopStoryJob.php`

Останавливает loop-историю.

Поток:
1. Находит активную запись `VkLoopStory` по `offer_id` и `is_active = true`.
2. Обновляет `is_active = false`.

Не делает VK API вызовов — только обновляет флаг в БД.

### CreateVkCommentJob

**Файл:** `app/Jobs/CreateVkCommentJob.php`

Создаёт комментарий под созданным постом.

Поток:
1. Через `task->parentTask` находит `VkWallPost`.
2. Вызывает `vkApi->createComment(ownerId, postId, message)` с фиксированным текстом: "Подробности по объекту уточняйте у агента в личных сообщениях 📩".
3. Сохраняет `external_id` = comment_id.

### CreateVkProductJob

**Файл:** `app/Jobs/CreateVkProductJob.php`

Создаёт товар (market item) в группах ВК.

Поток:
1. Загружает все группы из `VkGroup::all()`.
2. Создаёт `VkPostContext` для формирования названия, описания, цены.
3. Для каждой группы:
   - Вызывает `vkApi->createProduct(groupId, name, description, price, categoryId, images)`.
   - Создаёт запись `VkProduct` (`offer_id`, `agent_id`, `group_id`, `product_id`, `task_id`).
   - `usleep(350_000)` — ограничение rate limit (≈3 запроса/сек).
   - При ошибке в отдельной группе — логирует и продолжает со следующей.

Название: `"{category}, {address}"`. Описание: `"Подробности по телефону: {phone}\nАгент: {name}"`.

### EditVkProductJob

**Файл:** `app/Jobs/EditVkProductJob.php`

Редактирует существующие товары в группах.

Поток:
1. Находит все неархивированные `VkProduct` по `offer_id`.
2. Создаёт `VkPostContext` для актуальных данных.
3. Для каждого товара вызывает `vkApi->editProduct(groupId, productId, name, description, price, categoryId)`.
4. `usleep(350_000)` между вызовами.

### ArchiveVkProductJob

**Файл:** `app/Jobs/ArchiveVkProductJob.php`

Архивирует (удаляет) товары в группах.

Поток:
1. Находит все неархивированные `VkProduct` по `offer_id`.
2. Если товаров нет — задача сразу SUCCESS.
3. Если все товары в одной группе — использует VKScript `execute()` для пакетного удаления.
4. Если в разных группах — поочерёдно вызывает `vkApi->archiveProduct()` с `usleep(350_000)`.
5. Обновляет `is_archived = true` для всех товаров в БД.