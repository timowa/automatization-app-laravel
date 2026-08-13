# Review — Создать отсутствующий класс App\Scenarios\StatusRule

## Что проверить

1. Файл `app/Scenarios/StatusRule.php` существует и содержит абстрактный класс `App\Scenarios\StatusRule`.
2. Класс наследуется от `App\Scenarios\ScenarioRule` (не от `Scenario` и не напрямую от `OfferStatus`).
3. Конструктор принимает `OfferStatus` и сохраняет его в свойство с типизацией.
4. Метод `passes(OfferChanged $offerChanged): bool` реализован (не абстрактный) и корректно сравнивает статус текущего оффера с ожидаемым.
5. Класс объявлен как `abstract`.
6. Типизация свойств и параметров соблюдена (PHP 8.4, AGENTS.md правила).

## Какие файлы проверить

- `app/Scenarios/StatusRule.php` — новый файл (единственный изменённый файл).
- `app/Scenarios/ScenarioRule.php` — не должен измениться.
- `app/Scenarios/ActiveStatusRule/ActiveStatusRule.php` — не должен измениться.
- Остальные 5 статус-правил — не должны измениться.

## Ожидаемое поведение

После создания файла:

1. `new ActiveStatusRule()` — не вызывает Fatal Error.
2. `ScenarioFactory::list()` — возвращает 10 классов, все инстанцируются без ошибок.
3. `ScenarioResolver::resolve($offerChanged)` — корректно проверяет статус-правила для сценариев Sale, PriceChanged, AgentChanged, Booked, Sold, Feedback, Withdrawn, Delayed, Deleted.
4. Оффер со статусом ACTIVE → ActiveStatusRule::passes() возвращает true.
5. Оффер со статусом ARCHIVE → ArchiveStatusRule::passes() возвращает true.
6. Оффер со статусом не совпадающим с ожидаемым → passes() возвращает false.

## Потенциальные риски

1. **Offer::status() возвращает null** — `OfferStatus::tryFrom()` может вернуть null при невалидном значении в БД. Сравнение `null === OfferStatus::ACTIVE` даёт false — безопасно (сценарий не выбран).

2. **Тесты выявят скрытые проблемы** — после создания StatusRule ранее непроходящие тесты начнут выполняться и могут обнаружить другие проблемы в логике сценариев (не связанные с StatusRule).

3. ** PSR-4 автозагрузка** — файл должен находиться точно в `app/Scenarios/StatusRule.php` (не в подкаталоге), чтобы соответствовать namespace `App\Scenarios\StatusRule`.

## Сценарии тестирования

1. Создать оффер со статусом ACTIVE, предыдущего нет → должен выбран AnnouncementScenario (не использует StatusRule).
2. Создать оффер со статусом ACTIVE и ценой, предыдущего нет → должен выбран SaleScenario (использует ActiveStatusRule).
3. Создать оффер со статусом BOOKED, предыдущий со статусом ACTIVE и публикацией SALE → должен выбран BookedScenario.
4. Создать оффер со статусом ARCHIVE, предыдущий со статусом ACTIVE и публикацией SALE, первая запись архив → должен выбран SoldScenario.
5. Создать оффер со статусом REMOVED → должен выбран WithdrawnScenario.
6. Создать оффер со статусом DELAYED → должен выбран DelayedScenario.
7. Создать оффер со статусом DELETED → должен выбран DeletedScenario.