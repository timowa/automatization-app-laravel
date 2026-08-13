# Модуль приёма вебхука и работы с Offer

## Назначение

Приём входящих данных об объектах недвижимости через вебхук `POST /offer`, парсинг в DTO, валидация на дубли, сохранение в БД и запуск процесса автоматизации через event.

## Компоненты

### OfferController

**Файл:** `app/Http/Controllers/OfferController.php`

Единственный API-контроллер. Отвечает за приём вебхука.

- `offer(OfferWebhookRequest $request)` — принимает массив `offers`, передаёт в `ReceiveOfferWebhookAction`, возвращает `200 OK` или `502 Error`.
- `index()` — редирект на `/agents` (веб-панель).

Контроллер только принимает запрос и формирует ответ. Вся логика вынесена в Action.

### OfferWebhookRequest

**Файл:** `app/Http/Requests/OfferWebhookRequest.php`

FormRequest-валидация входящего массива `offers`. Проверяет обязательные поля: `id`, `code`, `url`, `agent.phone`, `location.city`, `location.address`, `deal`, `category`, `price`, `status`. Опциональные поля: `photos`, `stage`, `rooms`, `floor`, `floors`, площади, `deposit`, `commission`.

### ReceiveOfferWebhookAction

**Файл:** `app/Actions/ReceiveOfferWebhookAction.php`

Оркестрирует обработку массива офферов из вебхука.

Поток:
1. Логирует количество полученных офферов (канал `job`).
2. Для каждого оффера:
   - Парсит через `OfferParser::parse()` в `OfferData`.
   - При ошибке парсинга — логирует warning/error и пропускает.
   - Проверяет дубли: ищет существующую запись в `offers` по `code` + `price` + `stage` + `status`. Если найден — пропускает.
   - Находит предыдущий оффер по `code` (последний по `id`).
   - Создаёт `Offer` через `Offer::create()`.
   - Диспатчит `OfferCreatedEvent` с `prevOfferId` и `newOfferId`.

### OfferParser

**Файл:** `app/Helpers/OfferParser.php`

Преобразует сырой массив данных вебхука в `OfferData` DTO.

- Извлекает номер телефона агента, ищет `Agent` по `phone`.
- Если агент не найден — бросает `OfferParserException`.
- Парсит фото: извлекает URL из массива `photos`.
- Преобразует строковые значения enum-полей (`city`, `deal`, `category`, `status`) через `tryFromLabel()`.
- Возвращает `OfferData` с типизированными полями.

### OfferData

**Файл:** `app/DTO/OfferData.php`

DTO — снимок данных одного объекта из вебхука.

Поля: `offerId`, `code`, `stage`, `status` (OfferStatus), `city` (City), `agentId`, `price`, `commission`, `deposit`, `area`, `kitchenArea`, `livingArea`, `rooms`, `roomsOffered`, `floor`, `floors`, `images` (array), `deal` (Deal), `category` (Category), `location` (array).

Метод `getArray()` — преобразует DTO в массив для `Offer::create()` (ключи соответствуют fillable модели Offer).

### OfferCreatedEvent

**Файл:** `app/Events/OfferCreatedEvent.php`

Событие, диспатчится после создания нового Offer.

Поля:
- `?int $prevOfferId` — ID предыдущего оффера по этому коду (null если первого).
- `int $newOfferId` — ID нового оффера.

Обработчик: `ProcessOfferListener` (см. `docs/scenarios.md`).

### OfferChangesDetector

**Файл:** `app/Helpers/OfferChangesDetector.php`

Определяет, какие поля изменились между предыдущим и новым оффером.

Отслеживаемые поля: `price`, `status`, `stage`, `agent_id`.

- Если `previous === null` — возвращает все отслеживаемые поля (считается, что все изменились).
- Иначе возвращает массив полей, значения которых различаются.

Результат используется в `OfferChanged` DTO для `ScenarioResolver`.

### OfferChanged

**Файл:** `app/DTO/OfferChanged.php`

DTO, связывающий предыдущий и новый оффер с массивом изменений.

Поля:
- `?Offer $previous` — предыдущий оффер (null если первого).
- `Offer $current` — новый оффер.
- `array $changes` — список изменённых полей.

## Модель Offer

**Файл:** `app/Models/Offer.php`

Таблица: `offers`. Timestamps: только `created_at`.

Fillable: `offer_id`, `code`, `stage`, `status`, `price`, `area`, `kitchen_area`, `living_area`, `city`, `location`, `agent_id`, `images`, `deal`, `category`, `rooms`, `rooms_offered`, `floor`, `floors_total`, `commission`, `deposit`.

Casts: `images` → array, `location` → array, площади → float, числа → integer.

Методы:
- `city()`, `deal()`, `category()`, `status()` — возвращают соответствующие enum из значения БД.
- `agent()` — BelongsTo Agent.
- `vkWallPosts()` — HasMany VkWallPost.
- `publication()` — HasOne Publication.
- `getPrice()` — цена с коррекцией: если `deal === SALE` и цена < 1 000 000, умножает на 100 (копейки → рубли).
- `getBasePrice()` — сырая цена из БД без коррекции.
- `getAddressFromLocation()` — формирует строку адреса из `location` (город + адрес).

## Дедупликация

Проверка дублей в `ReceiveOfferWebhookAction` — ищет запись с совпадением по `code` + `price` + `stage` + `status`. Если найдена — оффер пропускается. Это обеспечивает идемпотентность: повторная отправка тех же данных не создаёт дубль.

## Уникальность объекта

Уникальность объекта недвижимости определяется полем `offers.code`. По одному объекту может быть создано несколько записей Offer (разные стадии, цены, статусы).