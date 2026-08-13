# Создать отсутствующий базовый класс App\Scenarios\StatusRule

## Описание задачи

В проекте отсутствует базовый класс `App\Scenarios\StatusRule`, от которого наследуются 6 статус-правил сценариев. Это приводит к Fatal Error при попытке загрузки любого сценария, использующего статус-правила (SaleScenario, PriceChangedScenario, AgentChangedScenario, BookedScenario, SoldScenario, FeedbackScenario, WithdrawnScenario, DelayedScenario, DeletedScenario).

## Контекст

При анализе модуля сценариев (docs/scenarios.md) обнаружено: 6 классов статус-правил (ActiveStatusRule, ArchiveStatusRule, BookedStatusRule, DelayedStatusRule, DeletedStatusRule, RemovedStatusRule) наследуются от `App\Scenarios\StatusRule` через `use App\Scenarios\StatusRule` и `extends StatusRule`. Однако файл `app/Scenarios/StatusRule.php` не существует — ни на диске, ни в git.

Эти статус-правила используются в 9 из 10 сценариев (все кроме AnnouncementScenario).

Существующие тесты (tests/Feature/WebhookScenarioTest.php, tests/Unit/BackendImplementationTest.php) проверяют сценарии, но тесты SaleScenario и PriceChangedScenario также зависят от ActiveStatusRule, который не загрузится без StatusRule.

## Требования

1. Создать абстрактный класс `App\Scenarios\StatusRule` в файле `app/Scenarios/StatusRule.php`.
2. Класс наследуется от `App\Scenarios\ScenarioRule`.
3. Конструктор принимает `OfferStatus` и сохраняет его в protected-свойство.
4. Метод `passes(OfferChanged $offerChanged): bool` проверяет `offerChanged->current->status()` на соответствие переданному `OfferStatus`.
5. Тесты должны проходить.