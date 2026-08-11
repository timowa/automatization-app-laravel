# Review Task: Проверка реализации нереализованных элементов

## Что проверить

Проверить корректность реализации всех новых компонентов и исправлений багов, описанных в tasks/backend.md. Убедиться, что реализация соответствует AGENTS.md и не ломает существующий функционал (сценарий "Анонс").

## Какие файлы проверить

### Баги (исправления)
- `app/DTO/OfferData.php` — getArray() возвращает floors_total (не floors), is_active убран
- `app/Helpers/OfferParser.php` — не передаёт is_active в конструктор OfferData
- `app/Models/Offer.php` — is_active убран из fillable и casts
- `app/Helpers/PublicationTaskDependenceInspector.php` — нет ссылки на несуществующий VK_PRODUCT, все 9 типов покрыты
- `app/Jobs/CreateVkPostJob.php` — нет finally-блока, SUCCESS не перетирается, $e не обращается если не определена
- `app/Jobs/CreateVkRepostJob.php` — то же
- `app/Jobs/CreateVkStoriesJob.php` — обновляет статус задачи, конструктор принимает taskId
- `app/Actions/ReceiveOfferWebhookAction.php` — корректно находит предыдущий оффер по code (последний по id), передаёт в event

### Сценарии
- `app/Scenarios/ScenarioFactory.php` — 10 сценариев в правильном порядке
- Каждый сценарий: `app/Scenarios/XxxScenario/XxxScenario.php` — type(), rules(), tasks() соответствуют AGENTS.md
- Каждое правило: метод passes() возвращает bool, логика соответствует AGENTS.md
- `app/Scenarios/PreviousPublicationRule.php` — корректно проверяет наличие предыдущей публикации

### Jobs
- Каждый новый Job: конструктор принимает int $taskId, обновляет статус PROCESSING → SUCCESS/FAILED, логирует по AGENTS.md, релизит зависимые задачи
- `app/Helpers/JobResolver.php` — все 9 типов замаплены, нет MissingException

### Шаблоны
- Каждый новый шаблон: реализует VkPostTemplateInterface, generate() возвращает string
- `app/Helpers/ScenarioVkPostTemplateResolver.php` — все сценарии с VK_POST замаплены

### Модели и миграции
- `app/Models/VkProduct.php` — $table, $fillable, $casts, relations
- `app/Models/VkLoopStory.php` — $table, $fillable, $casts, relations
- Миграции: vk_products, vk_loop_stories, add_is_active_to_offers — корректная структура

### VK API
- `app/Services/Vk/VkApiService.php` — новые методы используют VK SDK правильно, не логируют токены

### Логирование
- `app/Listeners/ProcessOfferListener.php` — лог "Публикация и задачи созданы" в канале job
- Все Jobs: лог info при успехе в канале job, warning при ошибке в канале job (без самой ошибки), error в канале vk

### Loop-story
- `app/Console/Commands/PublishLoopStoriesCommand.php` — корректная логика
- Scheduler зарегистрирован

## Ожидаемое поведение

### Флоу вебхука (без изменений)
1. POST /offer → ReceiveOfferWebhookAction → OfferParser → Offer::create → OfferCreatedEvent
2. ProcessOfferListener → OfferChangesDetector → ScenarioResolver → CreatePublicationAction → TaskDispatcher

### Сценарии
- Первый оффер по code (previous === null) → AnnouncementScenario (без проверки правил)
- Второй оффер (previous существует, статус актив, была публикация Анонс) → SaleScenario
- Цена снизилась на 10000+, был Sale → PriceChangedScenario
- Агент изменился, был Sale → AgentChangedScenario
- Статус BOOKED, был Sale → BookedScenario
- Статус ARCHIVE, был Sale → SoldScenario (или FeedbackScenario — зависит от порядка)
- Статус REMOVED → WithdrawnScenario
- Статус DELAYED → DelayedScenario
- Статус DELETED → DeletedScenario

### Задачи
- Независимые (VK_POST, VK_END_LOOP_STORY) → создаются в статусе PENDING
- Зависимые → создаются в статусе WAITING, переходят в PENDING при SUCCESS зависимой задачи
- Job обновляет статус: PENDING → PROCESSING → SUCCESS или FAILED
- При SUCCESS: release dependent tasks + dispatch pending tasks

### Логирование
- Канал job: info "Запрос получен", info "Оффер создан", info "Сценарий выбран", info "Публикация и задачи созданы", info об успехе задачи ИЛИ warning об ошибке (без самой ошибки)
- Каналы задач (vk, vkRepost): warning и error с деталями ошибки

## Потенциальные риски

1. **Регрессия "Анонс"** — изменение ScenarioFactory может сломать единственный работающий сценарий. Проверить: первый оффер по code всё ещё выбирает AnnouncementScenario.

2. **ScenarioResolver** — текущий код при previous===null возвращает $scenarios[0] без проверки правил. После добавления 10 сценариев $scenarios[0] всё ещё AnnouncementScenario. Проверить.

3. **OfferData fix** — изменение DTO может сломать парсер. Проверить: OfferParser::parse() создаёт OfferData без ошибок.

4. **finally-блок** — исправление может изменить поведение очереди. Проверить: при успехе задача остаётся SUCCESS, при ошибке FAILED.

5. **Зависимости задач** — изменение PublicationTaskDependenceInspector может сломать создание задач в CreatePublicationAction. Проверить: для каждого сценария задачи создаются с правильными зависимостями.

6. **PreviousPublicationRule** — если проверяет Publication по offer_id предыдущего оффера, но офферы по code могут быть разные записи. Проверить: поиск публикации по code, а не по offer_id.

7. **Sold vs Feedback** — одинаковые правила. Порядок в ScenarioFactory определяет приоритет. Проверить: SOLD перед FEEDBACK.

8. **CreateVkStoriesJob** — изменение конструктора с $postId на $taskId может сломать вызов. Проверить: JobResolver передаёт taskId, Job внутри находит VkWallPost через parentTask.

9. **Loop-story** — механика циклической публикации. Проверить: EndVkLoopStoryJob корректно останавливает loop-story, scheduler работает.

10. **Товары ВК** — API методы требуют прав токена. Проверить: обработка ошибок VK API не паникует, логирует в канал vk.

## Сценарии тестирования

### 1. Первый оффер (Анонс)
- Отправить вебхук с новым code, price=0, status=актив
- Ожидание: создан Offer, Publication (scenario=announcement), 3 задачи (VK_POST, VK_STORY, VK_REPOST)
- VK_POST → PENDING, VK_STORY → WAITING (depends on VK_POST), VK_REPOST → WAITING

### 2. Второй оффер (Продажа)
- Отправить вебхук с тем же code, price=695000, status=актив
- Ожидание: создан Offer, Publication (scenario=sale), 5 задач (VK_POST, VK_REPOST, VK_LOOP_STORY, VK_COMMENT, VK_CREATE_PRODUCT)
- VK_POST → PENDING, остальные → WAITING

### 3. Снижение цены (PriceChanged)
- Отправить вебхук с тем же code, price=685000 (на 10000 меньше), status=актив
- Ожидание: scenario=price_changed, 5 задач (VK_POST, VK_REPOST, VK_LOOP_STORY, VK_COMMENT, VK_EDIT_PRODUCT)
- VK_EDIT_PRODUCT → WAITING (depends on VK_CREATE_PRODUCT от предыдущей публикации)

### 4. Изменение агента (AgentChanged)
- Отправить вебхук с тем же code, другим агентом, status=актив
- Ожидание: scenario=agent_changed, 5 задач

### 5. Бронь (Booked)
- Отправить вебхук с тем же code, status=бронь
- Ожидание: scenario=booking, 5 задач (VK_POST, VK_REPOST, VK_STORY, VK_ARCHIVE_PRODUCT, VK_END_LOOP_STORY)

### 6. Продано (Sold)
- Отправить вебхук с тем же code, status=архив
- Ожидание: scenario=sold, 5 задач

### 7. Снято (Withdrawn)
- Отправить вебхук с тем же code, status=снято
- Ожидание: scenario=withdrawn, 2 задачи (VK_END_LOOP_STORY, VK_ARCHIVE_PRODUCT)

### 8. Отложено (Delayed)
- Отправить вебхук с тем же code, status=отложено
- Ожидание: scenario=delayed, 2 задачи

### 9. Удалено (Deleted)
- Отправить вебхук с тем же code, status=удалено
- Ожидание: scenario=deleted, 2 задачи

### 10. Дубликат оффера
- Отправить вебхук с тем же code, price, stage, status как предыдущий
- Ожидание: "Оффер с такими данными уже существует. Пропуск.", новый Offer не создан

### 11. Job execution — success
- VK_POST задача выполняется успешно
- Ожидание: статус SUCCESS, VkWallPost создан, лог info в канале job, зависимые задачи → PENDING

### 12. Job execution — failure
- VK_POST задача падает (нет токена)
- Ожидание: статус FAILED, лог warning в канале job (без ошибки), error в канале vk

### 13. Loop-story scheduler
- Создать VkLoopStory (is_active=true, last_published_at=4 дня назад)
- Запустить PublishLoopStoriesCommand
- Ожидание: история опубликована, last_published_at обновлена