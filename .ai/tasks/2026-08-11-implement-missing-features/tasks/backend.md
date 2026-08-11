# Backend Task: Реализация нереализованных элементов проекта

## Цель

Реализовать все элементы проекта, описанные в AGENTS.md, но не реализованные в коде. Исправить обнаруженные баги. Опираться на существующие паттерны (AnnouncementScenario, CreateVkPostJob, AnnouncementTemplate и т.д.).

## Контекст

Проект — автоматизация публикации недвижимости в ВК. Laravel 13.24, PHP 8.4, MySQL 8.x.
Полное описание проекта — в `AGENTS.md` в корне проекта. Описание процесса автоматизации, сценариев, задач и логирования — там.

Ключевые файлы для опоры:
- `app/Scenarios/AnnouncementScenario/AnnouncementScenario.php` — паттерн сценария
- `app/Scenarios/AnnouncementScenario/Rules/NewOfferRule.php` — паттерн правила
- `app/Jobs/CreateVkPostJob.php` — паттерн job'а (НО содержит баг в finally — см. ниже)
- `app/Services/Vk/WallPost/Templates/AnnouncementTemplate.php` — паттерн шаблона поста
- `app/Services/Vk/WallPost/Templates/SaleTemplate.php` — паттерн шаблона с условными блоками
- `app/Services/Vk/VkApiService.php` — сервис для VK API
- `app/Actions/CreatePublicationAction.php` — создание публикации и задач
- `app/Helpers/PublicationTaskDependenceInspector.php` — определение зависимостей задач (НО содержит баги — см. ниже)

## ЧАСТЬ 1. Баги (исправить)

### 1.1. OfferData DTO

Файл: `app/DTO/OfferData.php`

Проблема: `getArray()` возвращает `'floors' => $this->floors`, но модель Offer и миграция используют поле `floors_total` (не `floors`). Парсер передаёт `$data['floors']` в параметр `$floors`, но в БД колонка называется `floors_total`.

Примечание: поле `is_active` — легаси, оно заменено полем `status`. Парсер передаёт `$isActive` в конструктор, но это поле не используется. Нужно убрать `is_active` из парсера (`app/Helpers/OfferParser.php`) и из fillable модели Offer (`app/Models/Offer.php`). Миграция для `is_active` не нужна — колонки нет в БД и не должно быть.

Требуется:
1. В `getArray()` заменить `'floors' => $this->floors` на `'floors_total' => $this->floors`
2. Убрать параметр `$isActive` из вызова в OfferParser (строка с `isActive,` в конце `new OfferData(...)`)
3. Убрать `'is_active'` из `$fillable` модели Offer
4. Убрать `'is_active' => 'boolean'` из `$casts` модели Offer (если есть)

### 1.2. PublicationTaskDependenceInspector

Файл: `app/Helpers/PublicationTaskDependenceInspector.php`

Текущий код (баги):
```php
return match ($type) {
    PublicationTaskType::VK_STORY,
    PublicationTaskType::VK_COMMENT,
    PublicationTaskType::VK_REPOST,
    PublicationTaskType::VK_PRODUCT => PublicationTaskType::VK_POST,  // VK_PRODUCT не существует!
    default => null
};
```

Баги:
- `PublicationTaskType::VK_PRODUCT` — такого case нет в enum. Есть `VK_CREATE_PRODUCT`, `VK_EDIT_PRODUCT`, `VK_ARCHIVE_PRODUCT`.
- `VK_LOOP_STORY` отсутствует — должен зависеть от `VK_POST`.
- `VK_EDIT_PRODUCT` — должен зависеть от `VK_CREATE_PRODUCT`.
- `VK_ARCHIVE_PRODUCT` — должен зависеть от `VK_CREATE_PRODUCT`.
- `VK_END_LOOP_STORY` — независимая (job проверяет наличие активной loop-story).

Требуется исправить:
```php
return match ($type) {
    PublicationTaskType::VK_STORY,
    PublicationTaskType::VK_LOOP_STORY,
    PublicationTaskType::VK_COMMENT,
    PublicationTaskType::VK_REPOST,
    PublicationTaskType::VK_CREATE_PRODUCT => PublicationTaskType::VK_POST,

    PublicationTaskType::VK_EDIT_PRODUCT,
    PublicationTaskType::VK_ARCHIVE_PRODUCT => PublicationTaskType::VK_CREATE_PRODUCT,

    default => null  // VK_POST, VK_END_LOOP_STORY — независимые
};
```

### 1.3. finally-блоки в Jobs

Файлы: `app/Jobs/CreateVkPostJob.php`, `app/Jobs/CreateVkRepostJob.php`

Проблема: `finally` блок выполняется ВСЕГДА — даже при успешном выполнении. Это перетирает SUCCESS на FAILED и логирует ложный warning. Также `$e` может быть не определена если исключения не было (PHP error).

Пример бага в CreateVkPostJob:
```php
} finally {
    Log::channel('job')->warning('Пост не был опубликован');
    $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
}
```

Требуется: убрать finally-блок. Обрабатывать ошибки в catch-блоках — там ставить статус FAILED и логировать warning. Шаблон исправленного Job:

```php
public function handle(VkApiService $vkApi): void
{
    $task = PublicationTask::findOrFail($this->taskId);

    try {
        $task->update(['status' => PublicationTaskStatus::PROCESSING]);
        // ... бизнес-логика ...
        $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => ...]);
        Log::channel('job')->info('...', [...]);
        $this->taskDependencyResolver->release($this->taskId);
        $this->taskDispatcher->dispatch($task->publication_id);
    } catch (NotFoundException $e) {
        $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
        Log::channel('job')->warning('Ошибка создания поста по офферу ...', ['task_id' => $this->taskId]);
    } catch (VkApiException $e) {
        $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
        Log::channel('vk')->error($e->getMessage(), [...]);
        Log::channel('job')->warning('Ошибка создания поста по офферу ...', ['task_id' => $this->taskId]);
    } catch (\Throwable $e) {
        $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
        Log::channel('vk')->error($e->getMessage(), [...]);
        Log::channel('job')->warning('Ошибка создания поста по офферу ...', ['task_id' => $this->taskId]);
    }
}
```

Важно: по AGENTS.md лог в канале job должен быть warning и НЕ содержать саму ошибку — только факт. Сама ошибка пишется в канал vk.

Применить тот же паттерн к CreateVkRepostJob. CreateVkStoriesJob тоже проверить — там нет finally, но нет и обновления статуса задачи (FAILED). Нужно добавить обновление статуса.

### 1.4. ReceiveOfferWebhookAction — определение предыдущего оффера

Файл: `app/Actions/ReceiveOfferWebhookAction.php`

Проблема: для определения предыдущего оффера используется точное совпадение price/stage/status:
```php
$existingOffers = DB::table('offers')
    ->where('code', $offerData->code)
    ->where('price', $offerData->price)
    ->where('stage', $offerData->stage)
    ->where('status', $offerData->status)
    ->first();
```
Это НЕ предыдущий оффер — это проверка дубликата. Но в event передаётся `$existingOffers?->id` как prevOfferId. Если дубликат не найден (новый оффер), prevOfferId = null, и ScenarioResolver выберет Анонс. Но это неправильно — если по этому code уже была публикация, prevOffer должен быть последним оффером по этому code.

Требуется:
1. Проверка дубликата: оставить как есть (по code + price + stage + status)
2. Если не дубликат — найти предыдущий оффер по code (последний созданный):
```php
$prevOffer = DB::table('offers')
    ->where('code', $offerData->code)
    ->latest('id')
    ->first();
```
3. Создать новый Offer
4. Передать в event: `new OfferCreatedEvent($prevOffer?->id, $offer->id)`

## ЧАСТЬ 2. Новые файлы

### 2.1. Модель VkProduct + миграция

Создать миграцию: `database/migrations/2024_01_01_000007_create_vk_products_table.php`

```sql
vk_products:
  id (PK, auto increment)
  offer_id (int, not null)  -- FK к offers
  agent_id (int, not null)  -- FK к agents
  group_id (int, not null)  -- ID группы ВК
  product_id (int, not null)  -- ID товара в ВК
  task_id (int, not null)  -- ID задачи publication_tasks
  is_archived (boolean, default false)
  timestamps (created_at, updated_at)
```

Создать модель: `app/Models/VkProduct.php`
- $table = 'vk_products'
- $fillable = ['offer_id', 'agent_id', 'group_id', 'product_id', 'task_id', 'is_archived']
- $casts = ['is_archived' => 'boolean']
- relations: offer (BelongsTo), agent (BelongsTo), task (BelongsTo)

### 2.2. Таблица vk_loop_stories + модель

Создать миграцию: `database/migrations/2024_01_01_000008_create_vk_loop_stories_table.php`

```sql
vk_loop_stories:
  id (PK, auto increment)
  offer_id (int, not null)  -- FK к offers
  task_id (int, not null)  -- ID задачи publication_tasks (VK_LOOP_STORY)
  is_active (boolean, default true)
  last_published_at (datetime, nullable)
  timestamps
```

Создать модель: `app/Models/VkLoopStory.php`
- $table = 'vk_loop_stories'
- $fillable = ['offer_id', 'task_id', 'is_active', 'last_published_at']
- $casts = ['is_active' => 'boolean', 'last_published_at' => 'datetime']
- relations: offer (BelongsTo), task (BelongsTo)

### 2.3. Шаблоны постов (4 файла)

Каждый шаблон реализует `VkPostTemplateInterface` и метод `generate(VkPostContext $context): string`.
Опираться на SaleTemplate — там есть условные блоки через array_filter.

#### PriceChangedTemplate
Файл: `app/Services/Vk/WallPost/Templates/PriceChangedTemplate.php`
Содержание: пост о снижении цены. Структура аналогична SaleTemplate, но заголовок "ЦЕНА СНИЖЕНА!" и указание старой/новой цены. VkPostContext не содержит старую цену — нужен доступ к предыдущему офферу. Решение: добавить в VkPostContext поле `?int $oldPrice` и `?int $newPrice` (или переиспользовать price как newPrice и добавить oldPrice). Обновить VkPostContextFactory для подгрузки предыдущего оффера по code.

Псевдокод шаблона:
```
ЦЕНА СНИЖЕНА! {category} в г. {cityName}
Адрес: {address}
{details}
Старая цена: {oldPrice} руб.
Новая цена: {newPrice} руб.
Выгода: {diff} руб.
КОНТАКТЫ: ...
#broker_plus_post_{offerId}
```

#### BookedTemplate
Файл: `app/Services/Vk/WallPost/Templates/BookedTemplate.php`
Содержание: пост о брони объекта.
Псевдокод:
```
БРОНИРОВАНИЕ {category} в г. {cityName}
Адрес: {address}
{details}
Цена: {price} руб.
Статус: забронировано
КОНТАКТЫ: ...
#broker_plus_post_{offerId}
```

#### SoldTemplate
Файл: `app/Services/Vk/WallPost/Templates/SoldTemplate.php`
Содержание: пост о продаже объекта.
Псевдокод:
```
ПРОДАНО {category} в г. {cityName}
Адрес: {address}
{details}
Цена: {price} руб.
КОНТАКТЫ: ...
#broker_plus_post_{offerId}
```

#### FeedbackTemplate
Файл: `app/Services/Vk/WallPost/Templates/FeedbackTemplate.php`
Содержание: пост с отзывом о сделке.
Псевдокод:
```
ОТЗЫВ о сделке в г. {cityName}
{category}: {address}
Спасибо клиентам за доверие!
#broker_plus_post_{offerId}
```

### 2.4. ScenarioVkPostTemplateResolver — обновить

Файл: `app/Helpers/ScenarioVkPostTemplateResolver.php`

Добавить маппинг:
```php
return match ($scenarioType) {
    ScenarioType::ANNOUNCEMENT => new AnnouncementTemplate,
    ScenarioType::SALE => new SaleTemplate,
    ScenarioType::AGENT_CHANGED => new SaleTemplate,  -- AGENTS.md: шаблон SaleTemplate
    ScenarioType::PRICE_CHANGED => new PriceChangedTemplate,
    ScenarioType::BOOKING => new BookedTemplate,
    ScenarioType::SOLD => new SoldTemplate,
    ScenarioType::FEEDBACK => new FeedbackTemplate,
    default => throw new \Exception('Неподдерживаемый сценарий для шаблона поста')
};
```
Сценарии Withdrawn, Delayed, Deleted не имеют VK_POST — resolver для них не вызывается.

### 2.5. VkApiService — новые методы

Файл: `app/Services/Vk/VkApiService.php`

Добавить методы (опираться на https://dev.vk.com/ru/method):

```php
// Создать комментарий к посту
// https://dev.vk.com/ru/method/wall.createComment
public function createComment(int $ownerId, int $postId, string $message): array

// Создать товар в группе
// https://dev.vk.com/ru/method/market.add
public function createProduct(int $groupId, string $name, string $description, int $price, int $categoryId, array $imagePaths): array

// Редактировать товар
// https://dev.vk.com/ru/method/market.edit
public function editProduct(int $groupId, int $productId, string $name, string $description, int $price, int $categoryId): array

// Архивировать товар
// https://dev.vk.com/ru/method/market.delete
public function archiveProduct(int $groupId, int $productId): array
```

Для market.add требуется загрузка фото товара через `photos.getMarketUploadServer` → upload → `photos.saveMarketPhoto`. Реализовать аналогично wallPost (загрузка фото для поста).

### 2.6. Jobs (6 файлов)

Каждый Job следует паттерну CreateVkPostJob (БЕЗ finally — см. исправление в части 1.3).

#### CreateVkCommentJob
Файл: `app/Jobs/CreateVkCommentJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Зависит от: VK_POST (через PublicationTaskDependenceInspector)
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти родительскую задачу (VK_POST), получить external_id → VkWallPost
  3. Получить offer, agent, vkUser
  4. Установить токен
  5. Сформировать текст комментария (можно константу или отдельный шаблон)
  6. `$vkApi->createComment($post->owner_id, $post->post_id, $message)`
  7. Обновить статус SUCCESS, external_id = ID комментария
  8. Release dependent tasks, dispatch

#### CreateVkLoopStoryJob
Файл: `app/Jobs/CreateVkLoopStoryJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Зависит от: VK_POST
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти родительскую задачу (VK_POST) → VkWallPost
  3. Получить offer, agent, vkUser
  4. Сгенерировать баннер истории (аналогично CreateVkStoriesJob: VkStoriesContextFactory + VkStoriesGenerator)
  5. `$vkApi->storiesPost($post->getFullId(), $imagePath)`
  6. Создать запись VkLoopStory (is_active=true, last_published_at=now())
  7. Обновить статус SUCCESS
  8. Release dependent tasks, dispatch

#### EndVkLoopStoryJob
Файл: `app/Jobs/EndVkLoopStoryJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Независимая задача
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти offer через publication
  3. Найти VkLoopStory по offer_id where is_active=true
  4. Если нет — обновить статус SUCCESS (no-op)
  5. Если есть — обновить is_active=false
  6. Обновить статус SUCCESS
  7. Release dependent tasks, dispatch

#### CreateVkProductJob
Файл: `app/Jobs/CreateVkProductJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Зависит от: VK_POST
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти родительскую задачу (VK_POST) → VkWallPost → offer
  3. Получить agent, vkUser, группы (VkGroup::all())
  4. Для каждой группы:
     - Загрузить фото товара (photos.getMarketUploadServer → upload → photos.saveMarketPhoto)
     - `$vkApi->createProduct($groupId, $name, $description, $price, $categoryId, $imagePaths)`
     - Сохранить VkProduct (offer_id, agent_id, group_id, product_id, task_id)
  5. Обновить статус SUCCESS
  6. Release dependent tasks, dispatch
  7. Группировать запросы через execute() VKScript если возможно (но market.add требует загрузку фото, поэтому по одной)

#### EditVkProductJob
Файл: `app/Jobs/EditVkProductJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Зависит от: VK_CREATE_PRODUCT
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти родительскую задачу (VK_CREATE_PRODUCT)
  3. Найти все VkProduct по offer_id
  4. Для каждого: `$vkApi->editProduct($group_id, $product_id, ...)`
  5. Обновить статус SUCCESS
  6. Release dependent tasks, dispatch

#### ArchiveVkProductJob
Файл: `app/Jobs/ArchiveVkProductJob.php`
- Конструктор: `__construct(private readonly int $taskId)`
- Зависит от: VK_CREATE_PRODUCT
- Логика:
  1. Найти задачу, обновить статус на PROCESSING
  2. Найти offer через publication
  3. Найти все VkProduct по offer_id where is_archived=false
  4. Для каждого: `$vkApi->archiveProduct($group_id, $product_id)`
  5. Обновить is_archived=true
  6. Обновить статус SUCCESS
  7. Release dependent tasks, dispatch
  8. AGENTS.md: "запросы, которые можно сгруппировать — объединять в execute()". ArchiveProduct можно группировать — market.delete для нескольких товаров в одном execute().

### 2.7. JobResolver — обновить

Файл: `app/Helpers/JobResolver.php`

```php
return match ($type) {
    PublicationTaskType::VK_POST => CreateVKPostJob::class,
    PublicationTaskType::VK_STORY => CreateVkStoriesJob::class,
    PublicationTaskType::VK_REPOST => CreateVkRepostJob::class,
    PublicationTaskType::VK_COMMENT => CreateVkCommentJob::class,
    PublicationTaskType::VK_LOOP_STORY => CreateVkLoopStoryJob::class,
    PublicationTaskType::VK_END_LOOP_STORY => EndVkLoopStoryJob::class,
    PublicationTaskType::VK_CREATE_PRODUCT => CreateVkProductJob::class,
    PublicationTaskType::VK_EDIT_PRODUCT => EditVkProductJob::class,
    PublicationTaskType::VK_ARCHIVE_PRODUCT => ArchiveVkProductJob::class,
};
```

### 2.8. Общее правило PreviousPublicationRule

Файл: `app/Scenarios/PreviousPublicationRule.php`

```php
class PreviousPublicationRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        if ($offerChanged->previous === null) {
            return false;
        }
        // Проверить, что по данному offer (по code) существует Publication
        // со сценарием, который выше в иерархии ScenarioFactory
        $previousPublications = Publication::whereHas('offer', fn($q) =>
            $q->where('code', $offerChanged->current->code)
        )->pluck('scenario');

        // Получить список сценариев выше текущего
        // Если хотя бы одна предыдущая публикация имеет сценарий выше — проходит
        return $previousPublications->isNotEmpty();
    }
}
```

Точная реализация: проверять не просто "любая публикация", а публикация со сценарием, который стоит выше в иерархии ScenarioFactory. Но для упрощения можно проверять наличие любой публикации — т.к. если предыдущий оффер существует и он не первый, публикация уже была.

### 2.9. Сценарии (9 файлов + директории)

Каждый сценарий в своей директории: `app/Scenarios/XxxScenario/XxxScenario.php` + `Rules/`.

Порядок в ScenarioFactory (определяет иерархию статусов):

```php
public function list(): array
{
    return [
        AnnouncementScenario::class,
        SaleScenario::class,
        PriceChangedScenario::class,
        AgentChangedScenario::class,
        BookedScenario::class,
        SoldScenario::class,
        FeedbackScenario::class,
        WithdrawnScenario::class,
        DelayedScenario::class,
        DeletedScenario::class,
    ];
}
```

#### SaleScenario (Продажа)
Директория: `app/Scenarios/SaleScenario/`
Правила:
1. `NewOfferOrAnnouncementRule` — предыдущий оффер null ИЛИ предыдущая публикация по сценарию Анонс
2. `ActiveStatusRule` — status === OfferStatus::ACTIVE
Задачи: VK_POST, VK_REPOST, VK_LOOP_STORY, VK_COMMENT, VK_CREATE_PRODUCT
Шаблон: SaleTemplate (уже существует)

#### PriceChangedScenario (Изменилась цена)
Директория: `app/Scenarios/PriceChangedScenario/`
Правила:
1. `PreviousPublicationRule` — была публикация со сценарием "Продажа" (точнее: проверять scenario === SALE)
2. `PriceDecreasedRule` — новая цена меньше старой на 10000+
3. `ActiveStatusRule` — status === OfferStatus::ACTIVE
Задачи: VK_POST, VK_REPOST, VK_LOOP_STORY, VK_COMMENT, VK_EDIT_PRODUCT
Шаблон: PriceChangedTemplate

#### AgentChangedScenario (Изменился агент)
Директория: `app/Scenarios/AgentChangedScenario/`
Правила:
1. `PreviousPublicationRule` — была публикация со сценарием "Продажа"
2. `AgentChangedRule` — agent_id изменился (есть в $offerChanged->changes)
3. `ActiveStatusRule` — status === OfferStatus::ACTIVE
Задачи: VK_POST, VK_REPOST, VK_LOOP_STORY, VK_COMMENT, VK_EDIT_PRODUCT
Шаблон: SaleTemplate

#### BookedScenario (Бронь)
Директория: `app/Scenarios/BookedScenario/`
Правила:
1. `PreviousPublicationRule` — была публикация со сценарием "Продажа"
2. `BookedStatusRule` — status === OfferStatus::BOOKED
Задачи: VK_POST, VK_REPOST, VK_STORY, VK_ARCHIVE_PRODUCT, VK_END_LOOP_STORY
Шаблон: BookedTemplate

#### SoldScenario (Продано)
Директория: `app/Scenarios/SoldScenario/`
Правила:
1. `PreviousPublicationRule` — была публикация со сценарием "Продажа"
2. `ArchiveStatusRule` — status === OfferStatus::ARCHIVE
3. `FirstArchiveRecordRule` — первая запись по данному code со статусом архив (отличает Sold от Feedback: Sold срабатывает на первый переход в архив, Feedback — на последующие)
Задачи: VK_POST, VK_REPOST, VK_STORY, VK_ARCHIVE_PRODUCT, VK_END_LOOP_STORY
Шаблон: SoldTemplate

#### FeedbackScenario (Отзыв)
Директория: `app/Scenarios/FeedbackScenario/`
Правила:
1. `PreviousPublicationRule` — была публикация со сценарием "Продажа"
2. `ArchiveStatusRule` — status === OfferStatus::ARCHIVE
Задачи: VK_POST, VK_REPOST, VK_STORY, VK_ARCHIVE_PRODUCT, VK_END_LOOP_STORY
Шаблон: FeedbackTemplate

Внимание: Feedback и Sold имеют статус "архив". Различение: Sold имеет доп. правило "первая запись по code со статусом архив". Feedback срабатывает на последующие записи со статусом архив. Порядок в ScenarioFactory: SOLD перед FEEDBACK — Sold проверяется первым, если не подходит (не первая запись в архив) — проверяется Feedback.

#### WithdrawnScenario (Снято)
Директория: `app/Scenarios/WithdrawnScenario/`
Правила:
1. `RemovedStatusRule` — status === OfferStatus::REMOVED
Задачи: VK_END_LOOP_STORY, VK_ARCHIVE_PRODUCT
Шаблон: нет (VK_POST отсутствует)

#### DelayedScenario (Отложено)
Директория: `app/Scenarios/DelayedScenario/`
Правила:
1. `DelayedStatusRule` — status === OfferStatus::DELAYED
Задачи: VK_END_LOOP_STORY, VK_ARCHIVE_PRODUCT
Шаблон: нет

#### DeletedScenario (Удалено)
Директория: `app/Scenarios/DeletedScenario/`
Правила:
1. `DeletedStatusRule` — status === OfferStatus::DELETED
Задачи: VK_END_LOOP_STORY, VK_ARCHIVE_PRODUCT
Шаблон: нет

### 2.10. Общие правила — реализация

#### ActiveStatusRule
Файл: `app/Scenarios/ActiveStatusRule.php`
```php
public function passes(OfferChanged $offerChanged): bool
{
    return $offerChanged->current->status() === OfferStatus::ACTIVE;
}
```

#### BookedStatusRule
```php
return $offerChanged->current->status() === OfferStatus::BOOKED;
```

#### ArchiveStatusRule
```php
return $offerChanged->current->status() === OfferStatus::ARCHIVE;
```

#### RemovedStatusRule
```php
return $offerChanged->current->status() === OfferStatus::REMOVED;
```

#### DelayedStatusRule
```php
return $offerChanged->current->status() === OfferStatus::DELAYED;
```

#### DeletedStatusRule
```php
return $offerChanged->current->status() === OfferStatus::DELETED;
```

#### FirstArchiveRecordRule
Файл: `app/Scenarios/SoldScenario/Rules/FirstArchiveRecordRule.php`
Проверяет, что текущий оффер — первая запись по данному code со статусом архив.
```php
public function passes(OfferChanged $offerChanged): bool
{
    // Проверить, что нет предыдущих офферов по этому code со статусом архив
    // (до текущего)
    $count = Offer::where('code', $offerChanged->current->code)
        ->where('status', OfferStatus::ARCHIVE->value)
        ->where('id', '<', $offerChanged->current->id)
        ->count();
    return $count === 0;
}
```

Эти правила можно реализовать как один параметризованный класс `StatusRule` с конструктором, принимающим OfferStatus:
```php
class StatusRule extends ScenarioRule
{
    public function __construct(private readonly OfferStatus $expectedStatus) {}
    public function passes(OfferChanged $offerChanged): bool
    {
        return $offerChanged->current->status() === $this->expectedStatus;
    }
}
```
Но правила передаются как class-string в scenario->rules(), поэтому инстанцирование с параметром невозможно в текущей архитектуре. Решение: либо отдельные классы для каждого статуса, либо изменить архитектуру rules() для возврата инстансов. Рекомендую отдельные классы для соответствия существующему паттерну.

#### PriceDecreasedRule
Файл: `app/Scenarios/PriceChangedScenario/Rules/PriceDecreasedRule.php`
```php
public function passes(OfferChanged $offerChanged): bool
{
    if ($offerChanged->previous === null) return false;
    $oldPrice = $offerChanged->previous->getBasePrice();
    $newPrice = $offerChanged->current->getBasePrice();
    return ($oldPrice - $newPrice) >= 10000;
}
```

#### AgentChangedRule
Файл: `app/Scenarios/AgentChangedScenario/Rules/AgentChangedRule.php`
```php
public function passes(OfferChanged $offerChanged): bool
{
    return in_array('agent_id', $offerChanged->changes);
}
```

#### NewOfferOrAnnouncementRule
Файл: `app/Scenarios/SaleScenario/Rules/NewOfferOrAnnouncementRule.php`
```php
public function passes(OfferChanged $offerChanged): bool
{
    if ($offerChanged->previous === null) return true;
    // Проверить, что предыдущая публикация была по сценарию Анонс
    $prevPublication = Publication::where('offer_id', $offerChanged->previous->id)->first();
    return $prevPublication && $prevPublication->scenario === ScenarioType::ANNOUNCEMENT;
}
```

#### PreviousPublicationRule (для сценариев после Продажа)
Файл: `app/Scenarios/PreviousPublicationRule.php`
```php
public function passes(OfferChanged $offerChanged): bool
{
    if ($offerChanged->previous === null) return false;
    // Проверить наличие публикации по данному code со сценарием "Продажа" или выше
    $publications = Publication::whereHas('offer', fn($q) =>
        $q->where('code', $offerChanged->current->code)
    )->pluck('scenario');

    return $publications->contains(ScenarioType::SALE);
}
```

### 2.11. ProcessOfferListener — добавить лог

Файл: `app/Listeners/ProcessOfferListener.php`

После `$this->createPublicationAction->execute(...)` добавить:
```php
Log::channel('job')->info('Публикация и задачи созданы', [
    'offer_id' => $newOffer->id,
    'scenario' => $scenario->type()->value,
]);
```

### 2.12. CreateVkStoriesJob — обновить статус задачи

Файл: `app/Jobs/CreateVkStoriesJob.php`

Текущий код не обновляет статус PublicationTask. Добавить:
1. Найти задачу по $this->taskId (изменить конструктор с $postId на $taskId, как в других Jobs)
2. Обновить статус на PROCESSING в начале
3. Обновить статус на SUCCESS в конце
4. Добавить обработку ошибок по паттерну (catch + FAILED)
5. Release dependent tasks + dispatch

### 2.13. Loop-story scheduler

Создать команду: `app/Console/Commands/PublishLoopStoriesCommand.php`
- Найти все VkLoopStory where is_active=true AND last_published_at < now()-3days
- Для каждой: сгенерировать и опубликовать историю (аналог CreateVkLoopStoryJob)
- Обновить last_published_at = now()
- Логировать в канал job

Зарегистрировать в `app/Console/Kernel.php` (или routes/console.php для Laravel 11+) — раз в день в 10:00.

## Затрагиваемые файлы (сводка)

### Новые
- database/migrations/2024_01_01_000007_create_vk_products_table.php
- database/migrations/2024_01_01_000008_create_vk_loop_stories_table.php
- app/Models/VkProduct.php
- app/Models/VkLoopStory.php
- app/Scenarios/PreviousPublicationRule.php
- app/Scenarios/ActiveStatusRule.php (и другие StatusRule)
- app/Scenarios/SaleScenario/SaleScenario.php + Rules/
- app/Scenarios/PriceChangedScenario/PriceChangedScenario.php + Rules/
- app/Scenarios/AgentChangedScenario/AgentChangedScenario.php + Rules/
- app/Scenarios/BookedScenario/BookedScenario.php + Rules/
- app/Scenarios/SoldScenario/SoldScenario.php + Rules/
- app/Scenarios/FeedbackScenario/FeedbackScenario.php + Rules/
- app/Scenarios/WithdrawnScenario/WithdrawnScenario.php + Rules/
- app/Scenarios/DelayedScenario/DelayedScenario.php + Rules/
- app/Scenarios/DeletedScenario/DeletedScenario.php + Rules/
- app/Jobs/CreateVkCommentJob.php
- app/Jobs/CreateVkLoopStoryJob.php
- app/Jobs/EndVkLoopStoryJob.php
- app/Jobs/CreateVkProductJob.php
- app/Jobs/EditVkProductJob.php
- app/Jobs/ArchiveVkProductJob.php
- app/Services/Vk/WallPost/Templates/PriceChangedTemplate.php
- app/Services/Vk/WallPost/Templates/BookedTemplate.php
- app/Services/Vk/WallPost/Templates/SoldTemplate.php
- app/Services/Vk/WallPost/Templates/FeedbackTemplate.php
- app/Console/Commands/PublishLoopStoriesCommand.php

### Изменяемые
- app/DTO/OfferData.php
- app/Helpers/PublicationTaskDependenceInspector.php
- app/Helpers/JobResolver.php
- app/Helpers/ScenarioVkPostTemplateResolver.php
- app/Actions/ReceiveOfferWebhookAction.php
- app/Listeners/ProcessOfferListener.php
- app/Jobs/CreateVkPostJob.php
- app/Jobs/CreateVkRepostJob.php
- app/Jobs/CreateVkStoriesJob.php
- app/Scenarios/ScenarioFactory.php
- app/Services/Vk/VkApiService.php
- app/Services/Vk/WallPost/VkPostContext.php (добавить oldPrice для PriceChangedTemplate)
- app/Services/Vk/WallPost/VkPostContextFactory.php (подгрузка предыдущего оффера)

## API изменения
Нет новых endpoint'ов. Существующий POST /offer не меняется.

## Модели
- VkProduct (новая)
- VkLoopStory (новая)

## Миграции
- create_vk_products_table
- create_vk_loop_stories_table

## Тесты
- Проверить что ScenarioFactory::list() возвращает 10 сценариев в правильном порядке
- Проверить что JobResolver::resolve() не выбрасывает исключение для каждого PublicationTaskType
- Проверить что PublicationTaskDependenceInspector::inspect() возвращает правильные зависимости для каждого типа
- Проверить что ScenarioVkPostTemplateResolver::resolve() возвращает шаблон для каждого сценария с VK_POST
- Проверить что OfferParser не падает при передаче is_active
- Проверить что CreateVkPostJob не перетирает SUCCESS на FAILED

## Ограничения
- Не реализовывать frontend — задача backend-only
- Не менять endpoint'ы и маршруты
- Не логировать VK токены
- Rate limit VK API: не больше 3 запросов в секунду
- Группировать запросы для одного агента через execute() VKScript где возможно
- Публикации идемпотентны: проверять дубли перед созданием
- Имя сценария совпадает с именем директории
- Файлы PHP совпадают с именем класса
- Не оставлять dd(), var_dump(), die() в production-коде

## Out of scope
- Frontend изменения
- Изменение админ-панели
- Новые endpoint'ы
- Изменение валидации OfferWebhookRequest
- Настройка cron на сервере (только регистрация scheduler'а в Laravel)

## Definition of Done

### Баги
- [ ] OfferData: getArray() возвращает floors_total (не floors)
- [ ] is_active убран из OfferParser, модели Offer (fillable и casts)
- [ ] OfferParser успешно создаёт OfferData без ошибок
- [ ] PublicationTaskDependenceInspector: нет ссылки на VK_PRODUCT, добавлены VK_LOOP_STORY, VK_EDIT_PRODUCT, VK_ARCHIVE_PRODUCT
- [ ] CreateVkPostJob: нет finally-блока, SUCCESS не перетирается FAILED
- [ ] CreateVkRepostJob: нет finally-блока, SUCCESS не перетирается
- [ ] CreateVkStoriesJob: обновляет статус задачи (PROCESSING/SUCCESS/FAILED)
- [ ] ReceiveOfferWebhookAction: корректно находит предыдущий оффер по code

### Сценарии
- [ ] SaleScenario создан с 2 правилами и 5 задачами
- [ ] PriceChangedScenario создан с 3 правилами и 5 задачами
- [ ] AgentChangedScenario создан с 3 правилами и 5 задачами
- [ ] BookedScenario создан с 2 правилами и 5 задачами
- [ ] SoldScenario создан с 3 правилами и 5 задачами (включая FirstArchiveRecordRule)
- [ ] FeedbackScenario создан с 2 правилами и 5 задачами
- [ ] WithdrawnScenario создан с 1 правилом и 2 задачами
- [ ] DelayedScenario создан с 1 правилом и 2 задачами
- [ ] DeletedScenario создан с 1 правилом и 2 задачами
- [ ] ScenarioFactory::list() возвращает все 10 сценариев в правильном порядке

### Jobs
- [ ] CreateVkCommentJob создан
- [ ] CreateVkLoopStoryJob создан
- [ ] EndVkLoopStoryJob создан
- [ ] CreateVkProductJob создан
- [ ] EditVkProductJob создан
- [ ] ArchiveVkProductJob создан
- [ ] JobResolver маппит все 9 типов PublicationTaskType

### Шаблоны
- [ ] PriceChangedTemplate создан
- [ ] BookedTemplate создан
- [ ] SoldTemplate создан
- [ ] FeedbackTemplate создан
- [ ] ScenarioVkPostTemplateResolver обрабатывает все сценарии с VK_POST

### Модели и БД
- [ ] VkProduct модель и миграция созданы
- [ ] VkLoopStory модель и миграция созданы
- [ ] Колонка is_active добавлена в offers

### VK API
- [ ] VkApiService::createComment() реализован
- [ ] VkApiService::createProduct() реализован (с загрузкой фото)
- [ ] VkApiService::editProduct() реализован
- [ ] VkApiService::archiveProduct() реализован

### Логирование
- [ ] ProcessOfferListener логирует "Публикация и задачи созданы"
- [ ] Jobs логируют info при успехе и warning при ошибке в канале job (без самой ошибки)
- [ ] Ошибки VK API пишутся в канал vk

### Loop-story
- [ ] PublishLoopStoriesCommand создана
- [ ] Команда зарегистрирована в scheduler

### Проверка
- [ ] php artisan migrate работает без ошибок
- [ ] php artisan route:list работает без ошибок
- [ ] Ни один класс не содержит dd(), var_dump(), die()