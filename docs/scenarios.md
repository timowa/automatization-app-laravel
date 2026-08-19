# Модуль сценариев публикации

## Назначение

Определение сценария публикации на основе предыдущего оффера, нового оффера и изменений. Сценарий определяет набор задач (VK_POST, VK_STORY и т.д.), которые нужно выполнить.

## Архитектура

```
OfferCreatedEvent
    ↓
ProcessOfferListener
    ↓
OfferChangesDetector → OfferChanged DTO
    ↓
ScenarioResolver → Scenario
```

## Компоненты

### Scenario (abstract)

**Файл:** `app/Scenarios/Scenario.php`

Абстрактный базовый класс для всех сценариев.

Методы:
- `type(): ScenarioType` — возвращает тип сценария.
- `rules(): array` — возвращает массив классов правил (наследников `ScenarioRule`).
- `tasks(): PublicationTaskType[]` — возвращает массив типов задач.

### ScenarioRule (abstract)

**Файл:** `app/Scenarios/ScenarioRule.php`

Абстрактный базовый класс для всех правил.

Метод:
- `passes(OfferChanged $offerChanged): bool` — возвращает true, если правило выполнено.

Сценарий считается выбранным, если все его правила вернули true (нет ни одного false).

### ScenarioFactory

**Файл:** `app/Scenarios/ScenarioFactory.php`

Возвращает упорядоченный список классов сценариев. Порядок определяет иерархию — первый подходящий сценарий побеждает.

Порядок:
1. `AnnouncementScenario`
2. `RentScenario`
3. `SaleScenario`
4. `PriceChangedScenario`
5. `AgentChangedScenario`
6. `BookedScenario`
7. `SoldScenario`
8. `FeedbackScenario`
9. `WithdrawnScenario`
10. `DelayedScenario`
11. `DeletedScenario`

### ScenarioResolver

**Файл:** `app/Scenarios/ScenarioResolver.php`

Решает, какой сценарий применить к публикации.

Алгоритм:
1. Получает список сценариев из `ScenarioFactory::list()`.
2. Для каждого сценария создаёт экземпляр и проверяет все его правила.
3. Если все правила `passes()` вернули true — возвращает этот сценарий.
4. Если ни один не подошёл — возвращает null.

### ProcessOfferListener

**Файл:** `app/Listeners/ProcessOfferListener.php`

Слушатель `OfferCreatedEvent`. Реализует `ShouldQueue` (асинхронный).

Поток:
1. Загружает предыдущий и новый оффер.
2. Вызывает `OfferChangesDetector::detect()` для определения изменений.
3. Создаёт `OfferChanged` DTO.
4. Вызывает `ScenarioResolver::resolve()`.
5. Логирует выбранный сценарий (канал `job`).
6. Если сценарий найден — вызывает `CreatePublicationAction::execute()`.

Регистрация: `app/Providers/EventServiceProvider.php`.

### TaskDispatcher

**Файл:** `app/Scenarios/TaskDispatcher.php`

Диспатчит Job'ы для задач публикации в статусе `PENDING`.

- Выбирает все `publication_tasks` с `publication_id` и `status = PENDING`.
- Для каждой задачи через `JobResolver` определяет класс Job и диспатчит его.

Вызывается после создания публикации и после освобождения зависимых задач.

## Общие правила

### PreviousPublicationRule

**Файл:** `app/Scenarios/PreviousPublicationRule/PreviousPublicationRule.php`

Общее правило для всех сценариев кроме "Анонс" и "Продажа".

Проверяет, что по этому офферу (`code`) уже была публикация. Возвращает false, если `previous === null` или не существует `Publication` для оффера с тем же `code`.

### Статус-правила

Все статус-правила наследуются от `App\Scenarios\StatusRule` — абстрактного базового класса, который проверяет `offerChanged->current->status()` на соответствие заданному `OfferStatus`.

> ВНИМАНИЕ: файл `app/Scenarios/StatusRule.php` не существует на диске. Класс `App\Scenarios\StatusRule` на который ссылаются все статус-правила через `use App\Scenarios\StatusRule` — отсутствует. Это привёдет к Fatal Error при загрузке любого статус-правила. Базовый класс нужно создать.

Реализации статус-правил:

| Класс | Файл | Проверяемый статус |
|---|---|---|
| `ActiveStatusRule` | `app/Scenarios/ActiveStatusRule/ActiveStatusRule.php` | `OfferStatus::ACTIVE` |
| `ArchiveStatusRule` | `app/Scenarios/ArchiveStatusRule/ArchiveStatusRule.php` | `OfferStatus::ARCHIVE` |
| `BookedStatusRule` | `app/Scenarios/BookedStatusRule/BookedStatusRule.php` | `OfferStatus::BOOKED` |
| `RemovedStatusRule` | `app/Scenarios/RemovedStatusRule/RemovedStatusRule.php` | `OfferStatus::REMOVED` |
| `DelayedStatusRule` | `app/Scenarios/DelayedStatusRule/DelayedStatusRule.php` | `OfferStatus::DELAYED` |
| `DeletedStatusRule` | `app/Scenarios/DeletedStatusRule/DeletedStatusRule.php` | `OfferStatus::DELETED` |

## Сценарии

### 1. AnnouncementScenario (Анонс)

**Файл:** `app/Scenarios/AnnouncementScenario/AnnouncementScenario.php`

Тип: `ScenarioType::ANNOUNCEMENT`

Правила:
- `NewOfferRule` — `previous === null` и статус `ACTIVE`.

Задачи: `VK_POST`, `VK_STORY`, `VK_REPOST`.

Шаблон поста: `AnnouncementTemplate`.

**NewOfferRule:** `app/Scenarios/AnnouncementScenario/Rules/NewOfferRule.php` — первая запись по объекту, статус ACTIVE.

### 2. SaleScenario (Продажа)

**Файл:** `app/Scenarios/SaleScenario/SaleScenario.php`

Тип: `ScenarioType::SALE`

Правила:
- `NewOfferOrAnnouncementRule` — первый оффер или предыдущая публикация со сценарием ANNOUNCEMENT.
- `ActiveStatusRule` — статус ACTIVE.

Задачи: `VK_POST`, `VK_REPOST`, `VK_LOOP_STORY`, `VK_COMMENT`, `VK_CREATE_PRODUCT`.

Шаблон поста: `SaleTemplate`.

**NewOfferOrAnnouncementRule:** `app/Scenarios/SaleScenario/Rules/NewOfferOrAnnouncementRule.php` — если `previous === null` (true), иначе проверяет что предыдущая публикация имела сценарий ANNOUNCEMENT или SALE.

### 3. RentScenario (Сдача в аренду)

**Файл:** `app/Scenarios/RentScenario/RentScenario.php`

Тип: `ScenarioType::RENT`

Правила:
- `NewOfferOrAnnouncementRule` — первый оффер или предыдущая публикация со сценарием ANNOUNCEMENT.
- `ActiveStatusRule` — статус ACTIVE.
- `OfferHasPrice` — цена больше 0.
- `DealIsRentOutRule` — тип сделки `RENT_OUT` ("сдача").
- `HasDepositAndCommissionRule` — заполнены `deposit` и `commission`.

Задачи: `VK_POST`, `VK_REPOST`, `VK_LOOP_STORY`, `VK_COMMENT`, `VK_CREATE_PRODUCT`.

Шаблон поста: `RentTemplate`.

### 4. PriceChangedScenario (Изменилась цена)

**Файл:** `app/Scenarios/PriceChangedScenario/PriceChangedScenario.php`

Тип: `ScenarioType::PRICE_CHANGED`

Правила:
- `PreviousPublicationRule` — была публикация.
- `PriceDecreasedRule` — цена снизилась. Для SALE — на 10 000+, для RENT_OUT — на любую сумму.
- `ActiveStatusRule` — статус ACTIVE.

Задачи: `VK_POST`, `VK_REPOST`, `VK_LOOP_STORY`, `VK_COMMENT`, `VK_EDIT_PRODUCT`.

Шаблон поста: `PriceChangedTemplate`.

**PriceDecreasedRule:** `app/Scenarios/PriceChangedScenario/Rules/PriceDecreasedRule.php` — сравнивает `getBasePrice()` предыдущего и текущего оффера. Для SALE проверяет `(oldPrice - newPrice) >= 10000`, для RENT_OUT возвращает true при любом снижении.

### 4. AgentChangedScenario (Изменился агент)

**Файл:** `app/Scenarios/AgentChangedScenario/AgentChangedScenario.php`

Тип: `ScenarioType::AGENT_CHANGED`

Правила:
- `PreviousPublicationRule` — была публикация.
- `AgentChangedRule` — `agent_id` в массиве изменений.
- `ActiveStatusRule` — статус ACTIVE.

Задачи: `VK_POST`, `VK_REPOST`, `VK_LOOP_STORY`, `VK_COMMENT`, `VK_EDIT_PRODUCT`.

Шаблон поста: `SaleTemplate`.

**AgentChangedRule:** `app/Scenarios/AgentChangedScenario/Rules/AgentChangedRule.php` — проверяет `in_array('agent_id', $offerChanged->changes)`.

### 5. BookedScenario (Бронь)

**Файл:** `app/Scenarios/BookedScenario/BookedScenario.php`

Тип: `ScenarioType::BOOKING`

Правила:
- `PreviousPublicationRule` — была публикация.
- `BookedStatusRule` — статус BOOKED.

Задачи: `VK_POST`, `VK_REPOST`, `VK_STORY`, `VK_ARCHIVE_PRODUCT`, `VK_END_LOOP_STORY`.

Шаблон поста: `BookedTemplate`.

### 6. SoldScenario (Продано)

**Файл:** `app/Scenarios/SoldScenario/SoldScenario.php`

Тип: `ScenarioType::SOLD`

Правила:
- `PreviousPublicationRule` — была публикация.
- `ArchiveStatusRule` — статус ARCHIVE.
- `FirstArchiveRecordRule` — первая запись со статусом ARCHIVE по этому `code`.

Задачи: `VK_POST`, `VK_REPOST`, `VK_STORY`, `VK_ARCHIVE_PRODUCT`, `VK_END_LOOP_STORY`.

Шаблон поста: `SoldTemplate`.

**FirstArchiveRecordRule:** `app/Scenarios/SoldScenario/Rules/FirstArchiveRecordRule.php` — считает записи с тем же `code`, статусом ARCHIVE и `id < current.id`. Если 0 — правило выполнено.

### 7. FeedbackScenario (Отзыв)

**Файл:** `app/Scenarios/FeedbackScenario/FeedbackScenario.php`

Тип: `ScenarioType::FEEDBACK`

Правила:
- `PreviousPublicationRule` — была публикация.
- `ArchiveStatusRule` — статус ARCHIVE.

Задачи: `VK_POST`, `VK_REPOST`, `VK_STORY`, `VK_ARCHIVE_PRODUCT`, `VK_END_LOOP_STORY`.

Шаблон поста: `FeedbackTemplate`.

> FeedbackScenario и SoldScenario различаются только правилом `FirstArchiveRecordRule`. SoldScenario срабатывает на первую запись со статусом ARCHIVE, FeedbackScenario — на все последующие.

### 8. WithdrawnScenario (Снято с публикации)

**Файл:** `app/Scenarios/WithdrawnScenario/WithdrawnScenario.php`

Тип: `ScenarioType::WITHDRAWN`

Правила:
- `RemovedStatusRule` — статус REMOVED.

Задачи: `VK_END_LOOP_STORY`, `VK_ARCHIVE_PRODUCT`.

### 9. DelayedScenario (Публикация отложена)

**Файл:** `app/Scenarios/DelayedScenario/DelayedScenario.php`

Тип: `ScenarioType::DELAYED`

Правила:
- `DelayedStatusRule` — статус DELAYED.

Задачи: `VK_END_LOOP_STORY`, `VK_ARCHIVE_PRODUCT`.

### 10. DeletedScenario (Удалено)

**Файл:** `app/Scenarios/DeletedScenario/DeletedScenario.php`

Тип: `ScenarioType::DELETED`

Правила:
- `DeletedStatusRule` — статус DELETED.

Задачи: `VK_END_LOOP_STORY`, `VK_ARCHIVE_PRODUCT`.

> WithdrawnScenario, DelayedScenario, DeletedScenario идентичны по структуре задач, различаются только проверяемым статусом.