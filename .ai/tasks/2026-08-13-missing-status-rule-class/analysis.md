# Analysis — Создать отсутствующий базовый класс App\Scenarios\StatusRule

## Task overview

Создать отсутствующий абстрактный класс `App\Scenarios\StatusRule` — базовый класс для всех статус-правил сценариев. Без него 9 из 10 сценариев не работают.

## Current architecture

Иерархия правил сценариев:

```
ScenarioRule (abstract)
  ├── NewOfferRule                    — прямое наследование
  ├── NewOfferOrAnnouncementRule      — прямое наследование
  ├── PriceDecreasedRule              — прямое наследование
  ├── AgentChangedRule                — прямое наследование
  ├── PreviousPublicationRule         — прямое наследование
  ├── FirstArchiveRecordRule          — прямое наследование
  └── StatusRule (abstract) ← ОТСУТСТВУЕТ
        ├── ActiveStatusRule           (OfferStatus::ACTIVE)
        ├── ArchiveStatusRule          (OfferStatus::ARCHIVE)
        ├── BookedStatusRule           (OfferStatus::BOOKED)
        ├── DelayedStatusRule          (OfferStatus::DELAYED)
        ├── DeletedStatusRule          (OfferStatus::DELETED)
        └── RemovedStatusRule          (OfferStatus::REMOVED)
```

`ScenarioRule` (app/Scenarios/ScenarioRule.php) — абстрактный класс с методом `passes(OfferChanged $offerChanged): bool`.

`StatusRule` должен быть абстрактным наследником `ScenarioRule`, который инкапсулирует проверку `offerChanged->current->status() === $expectedStatus`.

Конкретные статус-правила передают нужный `OfferStatus` в конструктор родителя:

```php
class ActiveStatusRule extends StatusRule
{
    public function __construct()
    {
        parent::__construct(OfferStatus::ACTIVE);
    }
}
```

## Affected areas

- `app/Scenarios/StatusRule.php` — новый файл (создание).
- Все 6 статус-правил косвенно затрагиваются (перестанут падать при загрузке).
- 9 сценариев, использующих статус-правила, косвенно затрагиваются (перестанут падать при резолве).

Затронутые сценарии и их статус-правила:

| Сценарий | Статус-правило |
|---|---|
| SaleScenario | ActiveStatusRule |
| PriceChangedScenario | ActiveStatusRule |
| AgentChangedScenario | ActiveStatusRule |
| BookedScenario | BookedStatusRule |
| SoldScenario | ArchiveStatusRule |
| FeedbackScenario | ArchiveStatusRule |
| WithdrawnScenario | RemovedStatusRule |
| DelayedScenario | DelayedStatusRule |
| DeletedScenario | DeletedStatusRule |

## Existing implementation

`ScenarioRule` (app/Scenarios/ScenarioRule.php):
```php
abstract class ScenarioRule
{
    abstract public function passes(OfferChanged $offerChanged): bool;
}
```

`OfferChanged` DTO (app/DTO/OfferChanged.php) имеет поле `current` (Offer), у которого метод `status()` возвращает `OfferStatus`.

Пример существующего статус-правила (app/Scenarios/ActiveStatusRule/ActiveStatusRule.php):
```php
class ActiveStatusRule extends StatusRule
{
    public function __construct()
    {
        parent::__construct(OfferStatus::ACTIVE);
    }
}
```

Все 6 статус-правил идентичны по структуре — различаются только передаваемым `OfferStatus`.

## Architecture decisions

1. **Расположение файла**: `app/Scenarios/StatusRule.php` — на уровне с `Scenario.php`, `ScenarioRule.php`, `ScenarioFactory.php`, `ScenarioResolver.php`, `TaskDispatcher.php`. Это соответствует namespace `App\Scenarios` и PSR-4 автозагрузке.

2. **Наследование**: `StatusRule extends ScenarioRule` — сохраняет иерархию, все правила — наследники `ScenarioRule`.

3. **Реализация**: `StatusRule` — абстрактный класс (не интерфейс, не trait), так как наследники вызывают `parent::__construct()` с конкретным `OfferStatus`.

4. **Логика passes()**: сравнение `offerChanged->current->status()` с сохранённым `OfferStatus`. Метод `status()` модели `Offer` возвращает `OfferStatus::tryFrom((int) $this->getAttribute('status'))`.

## Dependencies

Задача не имеет зависимостей от других задач. Создаётся один файл, который исправляет ошибку загрузки существующих классов.

Execution order: единственная задача, не требует разделения на backend/frontend/review.

## Risks

1. **Offer::status() может вернуть null** — `OfferStatus::tryFrom()` возвращает null если значение в БД не соответствует ни одному case. В текущей реализации `status()` не имеет fallback на null. Если `tryFrom` вернёт null, сравнение `=== $expectedStatus` даст false — правило не пройдёт, что безопасно (сценарий не выбран).

2. **Тесты могут зависеть от текущего поведения** — тесты `WebhookScenarioTest` и `BackendImplementationTest` создают сценарии через `new $scenarioClass()`. Без `StatusRule` это вызывает Fatal Error. После создания класса тесты должны начать проходить (или выявить другие скрытые проблемы).

## Questions

Нет. Информации достаточно для реализации.

## Definition of Done

- [ ] Файл `app/Scenarios/StatusRule.php` создан
- [ ] Класс `App\Scenarios\StatusRule` — абстрактный, наследует `ScenarioRule`
- [ ] Конструктор принимает `OfferStatus`, сохраняет в protected-свойство
- [ ] Метод `passes()` сравнивает `offerChanged->current->status()` с переданным `OfferStatus`
- [ ] Тест `tests/Unit/BackendImplementationTest.php` проходит
- [ ] Тест `tests/Feature/WebhookScenarioTest.php` проходит (для сценариев не требующих VK API)