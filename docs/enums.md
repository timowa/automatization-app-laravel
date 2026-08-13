# Модуль перечислений (Enums)

## Назначение

Типизированные перечисления PHP 8.4 для статусов, типов и категорий.

## Трейт EnumHasLabel

**Файл:** `app/Traits/EnumHasLabel.php`

Статический метод `tryFromLabel(string $label): ?self` — обратный поиск enum по label (без учёта регистра). Используется при парсинге вебхука, когда приходят строковые значения ("актив", "продажа" и т.д.).

## Основные перечисления

### OfferStatus

**Файл:** `app/Enums/OfferStatus.php`

Тип: `int`. Трейт: `EnumHasLabel`.

| Case | Value | Label |
|---|---|---|
| `ACTIVE` | 1 | актив |
| `BOOKED` | 2 | бронь |
| `ARCHIVE` | 3 | архив |
| `REMOVED` | 4 | снято |
| `DELAYED` | 6 | отложено |
| `DELETED` | 7 | удалено |

> Значение 5 пропущено.

### PublicationTaskStatus

**Файл:** `app/Enums/PublicationTaskStatus.php`

Тип: `string`.

| Case | Value |
|---|---|
| `WAITING` | waiting |
| `PENDING` | pending |
| `PROCESSING` | processing |
| `SUCCESS` | success |
| `FAILED` | failed |

### PublicationTaskType

**Файл:** `app/Enums/PublicationTaskType.php`

Тип: `string`.

| Case | Value |
|---|---|
| `VK_POST` | vk_post |
| `VK_COMMENT` | vk_comment |
| `VK_STORY` | vk_story |
| `VK_LOOP_STORY` | vk_loop_story |
| `VK_END_LOOP_STORY` | vk_end_loop_story |
| `VK_REPOST` | vk_repost |
| `VK_CREATE_PRODUCT` | vk_create_product |
| `VK_EDIT_PRODUCT` | vk_edit_product |
| `VK_ARCHIVE_PRODUCT` | vk_archive_product |

### ScenarioType

**Файл:** `app/Enums/ScenarioType.php`

Тип: `string`.

| Case | Value |
|---|---|
| `ANNOUNCEMENT` | announcement |
| `SALE` | sale |
| `PRICE_CHANGED` | price_changed |
| `AGENT_CHANGED` | agent_changed |
| `BOOKING` | booking |
| `SOLD` | sold |
| `FEEDBACK` | feedback |
| `WITHDRAWN` | withdrawn |
| `DELAYED` | delayed |
| `DELETED` | deleted |

### Deal

**Файл:** `app/Enums/Deal.php`

Тип: `int`. Трейт: `EnumHasLabel`.

| Case | Value | Label |
|---|---|---|
| `SALE` | 1 | Продажа |
| `PURCHASE` | 2 | Покупка |
| `RENT_OUT` | 3 | Сдача |
| `RENT` | 4 | Съем |

### Category

**Файл:** `app/Enums/Category.php`

Тип: `int`. Трейт: `EnumHasLabel`.

| Case | Value | Label |
|---|---|---|
| `APARTMENT` | 1 | Квартира |
| `HOUSE` | 2 | Дом на земле |
| `ROOM` | 3 | Комната |
| `NEW_BUILDING` | 4 | Новостройка |
| `COMMERCIAL` | 5 | Коммерческий объект |

### City

**Файл:** `app/Enums/City.php`

Тип: `int`. Трейт: `EnumHasLabel`.

| Case | Value | Label | Alias |
|---|---|---|---|
| `ABAKAN` | 1 | Абакан | abakan |
| `KYZYL` | 2 | Кызыл | kyzyl |
| `CHIKAGO` | 3 | Черногорск | chernogorsk |

> Case `CHIKAGO` соответствует городу Черногорск (alias `chernogorsk`).

## VK-перечисления

### FriendStatus

**Файл:** `app/Enums/Vk/FriendStatus.php`

Тип: `int`. Трейт: `EnumHasLabel`.

| Case | Value | Label |
|---|---|---|
| `NOT_FRIEND` | 0 | Не является другом |
| `OUTGOING_REQUEST` | 1 | Отправлена заявка / подписка пользователю |
| `INCOMING_REQUEST` | 2 | Имеется входящая заявка / подписка от пользователя |
| `FRIEND` | 3 | Является другом |

Статический метод `labelFor(?int $value): ?string`.

### Relation

**Файл:** `app/Enums/Vk/Relation.php`

Тип: `int`. Значения статусов семейного положения VK.

### Sex

**Файл:** `app/Enums/Vk/Sex.php`

Тип: `int`. Значения пола VK (0 — не указан, 1 — женский, 2 — мужской).

### LastSeenPlatform

**Файл:** `app/Enums/Vk/LastSeenPlatform.php`

Тип: `int`. Платформа последнего входа VK.