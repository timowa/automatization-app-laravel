# Analysis: Реализация нереализованных элементов проекта

## Task overview

Проект автоматизации публикации недвижимости в ВК. Из 10 сценариев публикации реализован только 1 (Анонс). Из 9 типов задач реализованы 3 (VK_POST, VK_REPOST, VK_STORY). Также обнаружены баги в DTO, хелпере зависимостей и Job'ах. Требуется реализовать всё описанное в AGENTS.md, опираясь на существующие паттерны.

## Current architecture

### Полный флоу
```
Вебхук (POST /offer) → OfferParser → Offer::create → OfferCreatedEvent
  → ProcessOfferListener (ShouldQueue)
    → OfferChangesDetector::detect(prev, current)
    → ScenarioResolver::resolve(OfferChanged)
    → CreatePublicationAction::execute(offer, scenario)
      → Publication::create
      → foreach scenario->tasks(): PublicationTask::create (pending или waiting)
    → TaskDispatcher::dispatch(publicationId)
      → foreach pending tasks: JobResolver::resolve(type)::dispatch(taskId)
```

### Реализованные компоненты
- **AnnouncementScenario** — единственный сценарий. 1 правило (NewOfferRule: previous === null). 3 задачи: VK_POST, VK_STORY, VK_REPOST.
- **ScenarioFactory** — возвращает только [AnnouncementScenario::class].
- **CreatePublicationAction** — создаёт Publication и PublicationTask'и в транзакции. Зависимые задачи → WAITING, независимые → PENDING.
- **PublicationTaskDependenceInspector** — определяет зависимость задачи. Зависимые: VK_STORY, VK_COMMENT, VK_REPOST, VK_PRODUCT → VK_POST. Но содержит баги (см. ниже).
- **PublicationTaskDependencyResolver** — переводит WAITING → PENDING при завершении зависимой задачи.
- **TaskDispatcher** — диспатчит PENDING задачи через JobResolver.
- **JobResolver** — маппит только 3 типа: VK_POST → CreateVkPostJob, VK_STORY → CreateVkStoriesJob, VK_REPOST → CreateVkRepostJob.
- **CreateVkPostJob** — создаёт пост через VkApiService, сохраняет VkWallPost, обновляет статус задачи, релизит зависимые задачи, диспатчит их.
- **CreateVkRepostJob** — репост в группы через VKScript execute().
- **CreateVkStoriesJob** — генерация баннера через Intervention Image, загрузка через stories.getPhotoUploadServer.
- **VkApiService** — обёртка над VK PHP SDK. Реализованы: wallPost, storiesPost, createReposts (VKScript), getPostsStats, checkToken, sendTokensMessage.
- **ScenarioVkPostTemplateResolver** — маппит только ANNOUNCEMENT → AnnouncementTemplate.
- **Шаблоны постов:** AnnouncementTemplate, SaleTemplate, RentTemplate.
- **Шаблоны историй:** SaleStoriesTemplate, RentStoriesTemplate.
- **VkPostContext / VkPostContextFactory** — контекст для генерации поста из Offer.
- **VkStoriesContext / VkStoriesContextFactory** — контекст для генерации истории из VkWallPost.

### Модели и БД
- agents, offers, vk_users, vk_groups, vk_posts, vk_post_stats, publications, publication_tasks
- Нет таблицы vk_products и модели VkProduct.

## Affected areas

### Создание новых файлов
- `app/Scenarios/SaleScenario/` — сценарий + Rules
- `app/Scenarios/PriceChangedScenario/` — сценарий + Rules
- `app/Scenarios/AgentChangedScenario/` — сценарий + Rules
- `app/Scenarios/BookedScenario/` — сценарий + Rules
- `app/Scenarios/SoldScenario/` — сценарий + Rules
- `app/Scenarios/FeedbackScenario/` — сценарий + Rules
- `app/Scenarios/WithdrawnScenario/` — сценарий + Rules
- `app/Scenarios/DelayedScenario/` — сценарий + Rules
- `app/Scenarios/DeletedScenario/` — сценарий + Rules
- `app/Scenarios/PreviousPublicationRule.php` — общее правило (была публикация со статусом ниже)
- `app/Jobs/CreateVkCommentJob.php`
- `app/Jobs/CreateVkLoopStoryJob.php`
- `app/Jobs/EndVkLoopStoryJob.php`
- `app/Jobs/CreateVkProductJob.php`
- `app/Jobs/EditVkProductJob.php`
- `app/Jobs/ArchiveVkProductJob.php`
- `app/Services/Vk/WallPost/Templates/PriceChangedTemplate.php`
- `app/Services/Vk/WallPost/Templates/BookedTemplate.php`
- `app/Services/Vk/WallPost/Templates/SoldTemplate.php`
- `app/Services/Vk/WallPost/Templates/FeedbackTemplate.php`
- `app/Models/VkProduct.php`
- `database/migrations/____create_vk_products_table.php`

### Изменение существующих файлов
- `app/Scenarios/ScenarioFactory.php` — добавить все 10 сценариев в правильном порядке
- `app/Helpers/JobResolver.php` — добавить маппинг для 6 новых типов задач
- `app/Helpers/PublicationTaskDependenceInspector.php` — исправить баги, добавить недостающие зависимости
- `app/Helpers/ScenarioVkPostTemplateResolver.php` — добавить маппинг для новых сценариев
- `app/DTO/OfferData.php` — исправить конструктор и getArray()
- `app/Listeners/ProcessOfferListener.php` — добавить лог "Публикация и задачи созданы"
- `app/Actions/ReceiveOfferWebhookAction.php` — исправить логику определения предыдущего оффера
- `app/Jobs/CreateVkPostJob.php` — исправить finally-блок
- `app/Jobs/CreateVkRepostJob.php` — исправить finally-блок
- `app/Services/Vk/VkApiService.php` — добавить методы для товаров и комментариев

## Existing implementation (паттерны для опоры)

### Паттерн сценария
```php
class XxxScenario extends Scenario
{
    public function type(): ScenarioType { return ScenarioType::XXX; }
    public function rules(): array { return [Rule1::class, Rule2::class, ...]; }
    public function tasks(): array { return [PublicationTaskType::VK_POST, ...]; }
}
```
Каждый сценарий в своей директории: `app/Scenarios/XxxScenario/XxxScenario.php` + `Rules/`.

### Паттерн правила
```php
class XxxRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool { ... }
}
```

### Паттерн Job'а
```php
class CreateVkXxxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public function __construct(private readonly int $taskId) { }
    public function handle(VkApiService $vkApi): void
    {
        try {
            $task = PublicationTask::findOrFail($this->taskId);
            $task->update(['status' => PublicationTaskStatus::PROCESSING]);
            // ... бизнес-логика ...
            $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => ...]);
            Log::channel('job')->info('...');
            $this->taskDependencyResolver->release($this->taskId);
            $this->taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            Log::channel('job')->warning($e->getMessage(), ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            Log::channel('vk')->error($e->getMessage(), [...]);
        } catch (\Throwable $e) {
            Log::channel('vk')->error($e->getMessage(), [...]);
        }
        // НЕТ finally-блока — статус обновляется только в try или catch
    }
}
```

### Паттерн шаблона поста
```php
class XxxTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        return <<<TEXT
        ...текст поста с подстановкой $context->...
        TEXT;
    }
}
```

### Паттерн шаблона историй
```php
class XxxStoriesTemplate implements VkStoriesTemplateInterface
{
    public function getDetails(VkStoriesContext $context): string { ... }
    public function getPrice(VkStoriesContext $context): string { ... }
}
```

## Architecture decisions

### 1. Порядок сценариев в ScenarioFactory
Статусы сценариев определяются порядком в массиве. Порядок определяет "иерархию" — сценарий ниже по списку требует публикацию выше по списку.

Порядок по AGENTS.md:
1. ANNOUNCEMENT (Анонс)
2. SALE (Продажа)
3. PRICE_CHANGED (Изменилась цена)
4. AGENT_CHANGED (Изменился агент)
5. BOOKING (Бронь)
6. SOLD (Продано)
7. FEEDBACK (Отзыв)
8. WITHDRAWN (Снято)
9. DELAYED (Отложено)
10. DELETED (Удалено)

### 2. Общее правило "была публикация со статусом выше"
Для всех сценариев кроме Анонс и Продажа: сценарий выполняется только если была публикация со статусом выше (раньше в списке ScenarioFactory).

Реализация: `PreviousPublicationRule` — проверяет, что по данному offer существует Publication с типом сценария, который выше в иерархии.

Для сценариев "Снято", "Отложено", "Удалено" — общее правило НЕ применяется (они определяются только по статусу Offer).

### 3. Зависимости задач (PublicationTaskDependenceInspector)
- VK_POST — независимая
- VK_REPOST — зависит от VK_POST
- VK_STORY — зависит от VK_POST
- VK_LOOP_STORY — зависит от VK_POST
- VK_COMMENT — зависит от VK_POST
- VK_CREATE_PRODUCT — зависит от VK_POST (нужен пост для ссылки)
- VK_EDIT_PRODUCT — зависит от VK_CREATE_PRODUCT (нужен ID товара для редактирования)
- VK_ARCHIVE_PRODUCT — зависит от VK_CREATE_PRODUCT (нужен ID товара для архивации)
- VK_END_LOOP_STORY — зависит от VK_LOOP_STORY (нужно остановить активную loop-story)
- VK_END_LOOP_STORY — может быть независимой, если нет loop-story. Решение: делать независимой, в Job проверять наличие активной loop-story.

Решение: VK_END_LOOP_STORY — независимая. Job проверяет наличие активной loop-story и если нет — завершается успешно (no-op).

### 4. Модель VkProduct
Таблица vk_products:
- id (PK)
- offer_id (FK → offers)
- agent_id (FK → agents)
- group_id (int) — ID группы ВК
- product_id (int) — ID товара в ВК
- task_id (FK → publication_tasks) — задача создания
- is_archived (bool, default false)
- timestamps

### 5. VK_LOOP_STORY — механика
Loop-story — это история, которая публикуется раз в 3 дня. Механика:
- CreateVkLoopStoryJob — создаёт историю, сохраняет информацию о loop-story (нужна таблица или флаг в publication_tasks).
- EndVkLoopStoryJob — останавливает циклическую публикацию.
- Планировщик (scheduler) — раз в 3 дня проверяет активные loop-stories и публикует.

Решение: таблица `vk_loop_stories` (id, offer_id, publication_task_id, is_active, last_published_at, timestamps). Scheduler-команда раз в 3 дня публикует активные loop-stories.

### 6. Баги — детали

#### OfferData DTO
- getArray() возвращает 'floors' => $this->floors, но Offer model и миграция используют 'floors_total'.
- is_active — легаси, заменено полем status. Нужно убрать из парсера и модели Offer.

#### PublicationTaskDependenceInspector
- VK_PRODUCT не существует в PublicationTaskType enum. Нужно: VK_CREATE_PRODUCT → VK_POST.
- VK_LOOP_STORY не указан как зависимый от VK_POST.
- VK_EDIT_PRODUCT, VK_ARCHIVE_PRODUCT — зависят от VK_CREATE_PRODUCT.
- VK_END_LOOP_STORY — независимая (no-op если нет активной loop-story).

#### finally-блоки в Jobs
- finally выполняется ВСЕГДА — после успешного try логирует warning "не опубликован" и ставит FAILED, перетирая SUCCESS.
- $e может быть не определена если исключения не было.
- Решение: убрать finally, обрабатывать ошибку в catch-блоках, ставить FAILED там.

## Dependencies

```
Backend:
1. Исправить баги (OfferData, DependenceInspector, finally-блоки)
2. Создать модель VkProduct + миграцию
3. Создать 4 шаблона постов
4. Обновить ScenarioVkPostTemplateResolver
5. Обновить JobResolver
6. Создать 6 Jobs
7. Добавить методы в VkApiService (товары, комментарии)
8. Создать 9 сценариев + правила
9. Обновить ScenarioFactory
10. Исправить ReceiveOfferWebhookAction (определение prevOffer)

Execution order: 1→2→3→4→5→6→7→8→9→10
```

Шаги 3 и 4 можно делать параллельно. Шаги 6 и 7 связаны. Шаги 8 и 9 связаны.

## Risks

1. **Регрессия сценария "Анонс"** — изменение ScenarioFactory и ScenarioResolver может сломать единственный работающий сценарий.
2. **ScenarioResolver логика** — текущий код при `$offerChanged->previous === null` возвращает `$scenarios[0]` без проверки правил. Нужно сохранить это поведение для Анонс.
3. **finally-блоки** — исправление может изменить поведение очереди (retry, release).
4. **Товары ВК** — API методы для товаров могут требовать дополнительных прав токена.
5. **Loop-story** — механика циклической публикации требует scheduler, что добавляет новый компонент.
6. **Логи** — AGENTS.md требует лог "Публикация и задачи созданы" в канале job, которого сейчас нет в ProcessOfferListener.
7. **OfferData fix** — изменение DTO может сломать существующий парсер.

## Questions

1. **Шаблоны постов для Withdrawn/Delayed/Deleted** — в AGENTS.md для этих сценариев не указаны шаблоны (только VK_END_LOOP_STORY + VK_ARCHIVE_PRODUCT, без VK_POST). Подтверждено: шаблоны не нужны.
2. **Sold vs Feedback** — РЕШЕНО. Sold имеет доп. правило "первая запись по Offer со статусом архив". Feedback срабатывает на последующие записи со статусом архив. Порядок в ScenarioFactory: SOLD перед FEEDBACK.
3. **Loop-story scheduler** — AGENTS.md не описывает механизм scheduler'а. Реализую как artisan-команду + scheduler.

## Definition of Done

- [ ] Все 10 сценариев созданы и зарегистрированы в ScenarioFactory
- [ ] Все правила сценариев созданы
- [ ] Общее правило "была публикация выше" создано и применяется к нужным сценариям
- [ ] Все 9 типов задач маппятся в JobResolver
- [ ] Все 6 новых Jobs созданы по паттерну существующих
- [ ] 4 новых шаблона постов созданы
- [ ] ScenarioVkPostTemplateResolver обрабатывает все сценарии с VK_POST
- [ ] Модель VkProduct + миграция созданы
- [ ] PublicationTaskDependenceInspector исправлен и покрывает все типы задач
- [ ] OfferData DTO исправлен (floors_total, is_active убран)
- [ ] finally-блоки в CreateVkPostJob и CreateVkRepostJob исправлены
- [ ] ReceiveOfferWebhookAction корректно определяет предыдущий оффер
- [ ] Лог "Публикация и задачи созданы" добавлен в ProcessOfferListener
- [ ] Методы для товаров и комментариев добавлены в VkApiService
- [ ] Механика loop-story реализована (таблица, scheduler)
- [ ] php artisan build (или аналог) проходит без ошибок