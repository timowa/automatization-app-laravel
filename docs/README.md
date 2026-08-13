# Техническая документация проекта vk19-app

Документация по модулям проекта автоматизации публикации объектов недвижимости во ВКонтакте.

## Структура документации

| Файл | Модуль |
|---|---|
| [webhook-and-offer.md](webhook-and-offer.md) | Приём вебхука, парсинг, DTO, модель Offer, дедупликация |
| [scenarios.md](scenarios.md) | Сценарии публикации: правила, резолвер, 10 сценариев |
| [publications-and-tasks.md](publications-and-tasks.md) | Публикации, задачи, зависимости, жизненный цикл |
| [jobs.md](jobs.md) | Jobs — асинхронное выполнение VK API задач |
| [vk-api-service.md](vk-api-service.md) | VkApiService — обёртка над VK PHP SDK |
| [vk-wallpost-templates.md](vk-wallpost-templates.md) | Шаблоны постов и генерация текста |
| [vk-stories.md](vk-stories.md) | Шаблоны и генерация историй (баннеры) |
| [models.md](models.md) | Модели Eloquent и связи |
| [enums.md](enums.md) | Перечисления (статусы, типы, категории) |
| [actions.md](actions.md) | Actions — слой бизнес-логики |
| [admin-panel.md](admin-panel.md) | Админ-панель: контроллеры, middleware, авторизация |
| [console-commands.md](console-commands.md) | Artisan-команды |
| [helpers.md](helpers.md) | Вспомогательные классы, функции, трейты, исключения |

## Общий поток

```
Вебхук (POST /offer) массив offers
    ↓
OfferParser → OfferData DTO → валидация дублей → Offer::create()
    ↓
OfferCreatedEvent → ProcessOfferListener (async)
    ↓
OfferChangesDetector → OfferChanged DTO
    ↓
ScenarioResolver → Scenario
    ↓
CreatePublicationAction → Publication + PublicationTask[]
    ↓
TaskDispatcher → Job::dispatch() (независимые задачи)
    ↓
Job: PROCESSING → SUCCESS → DependencyResolver → PENDING зависимых
    ↓
TaskDispatcher → Job::dispatch() (зависимые задачи)
```