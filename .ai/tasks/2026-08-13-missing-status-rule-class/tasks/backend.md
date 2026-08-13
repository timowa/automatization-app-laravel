# Backend Task — Создать отсутствующий класс App\Scenarios\StatusRule

## Цель

Создать абстрактный класс `App\Scenarios\StatusRule` в файле `app/Scenarios/StatusRule.php`, от которого наследуются 6 статус-правил сценариев. Без этого класса 9 из 10 сценариев падают с Fatal Error.

## Контекст

В проекте 6 классов статус-правил (ActiveStatusRule, ArchiveStatusRule, BookedStatusRule, DelayedStatusRule, DeletedStatusRule, RemovedStatusRule) наследуются от `App\Scenarios\StatusRule` через `extends StatusRule`. Но файла `app/Scenarios/StatusRule.php` не существует.

Эти правила используются в 9 сценариях: SaleScenario, PriceChangedScenario, AgentChangedScenario, BookedScenario, SoldScenario, FeedbackScenario, WithdrawnScenario, DelayedScenario, DeletedScenario.

## Существующая реализация

Абстрактный базовый класс `ScenarioRule` (app/Scenarios/ScenarioRule.php):
```php
abstract class ScenarioRule
{
    abstract public function passes(OfferChanged $offerChanged): bool;
}
```

DTO `OfferChanged` (app/DTO/OfferChanged.php):
- `?Offer $previous` — предыдущий оффер (null если первого).
- `Offer $current` — новый оффер.
- `array $changes` — список изменённых полей.

Модель `Offer::status()` (app/Models/Offer.php):
```php
public function status(): OfferStatus
{
    return OfferStatus::tryFrom((int) $this->getAttribute('status'));
}
```

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

Все 6 статус-правил идентичны — различаются только передаваемым `OfferStatus`.

## Необходимые изменения

Создать один файл: `app/Scenarios/StatusRule.php`.

Псевдокод:
```
namespace App\Scenarios;

use App\DTO\OfferChanged;
use App\Enums\OfferStatus;

abstract class StatusRule extends ScenarioRule
{
    protected OfferStatus $expectedStatus;

    public function __construct(OfferStatus $expectedStatus)
    {
        $this->expectedStatus = $expectedStatus;
    }

    public function passes(OfferChanged $offerChanged): bool
    {
        return $offerChanged->current->status() === $this->expectedStatus;
    }
}
```

> ВАЖНО: Метод `Offer::status()` возвращает `OfferStatus::tryFrom(...)` — может вернуть null если значение в БД невалидно. Сравнение null === OfferStatus даст false — это безопасное поведение (правило не пройдёт, сценарий не выбран).

## Затрагиваемые файлы

- `app/Scenarios/StatusRule.php` — НОВЫЙ файл (создание).

Никакие другие файлы не изменяются.

## API изменения

Нет.

## Модели

Нет.

## Миграции

Нет.

## Тесты

Запустить существующие тесты:

```bash
# Unit-тесты (проверяют ScenarioFactory, которая инстанцирует сценарии)
php artisan test --filter=BackendImplementationTest

# Feature-тесты (проверяют полный flow вебхука → сценарий)
php artisan test --filter=WebhookScenarioTest
```

Тест `BackendImplementationTest::test_scenario_factory_returns_ten_scenarios_in_order` создаёт экземпляры всех 10 сценариев через `new $scenario` — без StatusRule это падает.

Тест `WebhookScenarioTest` тестирует сценарии Sale, PriceChanged, AgentChanged, Sold, Feedback — все используют статус-правила.

## Ограничения

- Не изменять существующие статус-правила.
- Не изменять ScenarioRule.
- Не изменять сценарии.
- Создать только один файл.
- Класс должен быть abstract.
- Следовать правилам кода из AGENTS.md (типизация свойств, PascalCase).

## Out of scope

- Рефакторинг статус-правил.
- Изменение иерархии правил.
- Добавление новых правил или сценариев.
- Изменение логики Offer::status().

## Definition of Done

- [x] Файл `app/Scenarios/StatusRule.php` создан
- [x] Класс `App\Scenarios\StatusRule` — abstract, extends `ScenarioRule`
- [x] Конструктор принимает `OfferStatus`, сохраняет в protected-свойство
- [x] Метод `passes()` сравнивает `offerChanged->current->status()` с переданным `OfferStatus`
- [x] Тест `tests/Unit/BackendImplementationTest.php` проходит
- [ ] Тест `tests/Feature/WebhookScenarioTest.php` проходит для сценариев, не требующих VK API