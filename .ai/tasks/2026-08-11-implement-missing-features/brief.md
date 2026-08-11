# Brief: Реализация нереализованных элементов проекта

## Дата
2026-08-11

## Источник
AGENTS.md — полное описание реализации проекта автоматизации публикации недвижимости в ВК.

## Постановка
Проект описан в AGENTS.md, но значительная часть описанного не реализована. Реализован только сценарий "Анонс" и 3 job'а (VK_POST, VK_REPOST, VK_STORY). Остальные 9 сценариев, 6 job'ов, 4 шаблона постов, модель товаров и несколько багов требуют реализации.

## Принцип
Для каждого нереализованного элемента существует реализованный пример (AnnouncementScenario, CreateVkPostJob, AnnouncementTemplate и т.д.). Реализация должна опираться на созданные решения и паттерны.

## Состав работ

### Баги (исправить)
1. OfferData DTO — несоответствие параметров парсера и конструктора, неправильный ключ floors/floors_total, отсутствие is_active
2. PublicationTaskDependenceInspector — ссылка на несуществующий VK_PRODUCT, отсутствие VK_LOOP_STORY, VK_EDIT_PRODUCT, VK_ARCHIVE_PRODUCT
3. CreateVkPostJob и CreateVkRepostJob — finally-блок всегда выполняется, перетирая SUCCESS и логируя ложный warning

### Реализовать (новое)
1. 9 сценариев: Sale, PriceChanged, AgentChanged, Booked, Sold, Feedback, Withdrawn, Delayed, Deleted
2. Правила для каждого сценария
3. Общее правило "была публикация со статусом ниже" (для всех кроме Анонс и Продажа)
4. 6 jobs: CreateVkComment, CreateVkLoopStory, EndVkLoopStory, CreateVkProduct, EditVkProduct, ArchiveVkProduct
5. 4 шаблона постов: PriceChangedTemplate, BookedTemplate, SoldTemplate, FeedbackTemplate
6. Модель VkProduct + миграция таблицы vk_products
7. JobResolver — маппинг всех 9 типов задач
8. ScenarioVkPostTemplateResolver — маппинг всех сценариев на шаблоны
9. ScenarioFactory — регистрация всех 10 сценариев в правильном порядке

## Ограничения
- Не писать production-код в task-файлах (только архитектурные решения, псевдокод, примеры форматов)
- Backend-агент должен выполнить задачу без чтения analysis.md
- Опираться на существующие паттерны проекта