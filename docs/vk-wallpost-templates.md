# Модуль шаблонов постов и генерации текста

## Назначение

Генерация текста постов для публикации на стене ВКонтакте на основе данных оффера и выбранного сценария.

## Архитектура

```
VkPostContextFactory → VkPostContext
                            ↓
ScenarioVkPostTemplateResolver → VkPostTemplateInterface
                            ↓
VkPostGenerator::generate(context, template) → string
```

## Компоненты

### VkPostContext

**Файл:** `app/Services/Vk/WallPost/VkPostContext.php`

Контекст генерации поста — типизированные данные оффера для подстановки в шаблон.

Поля: `address`, `rooms`, `area`, `kitchenArea`, `floor`, `floorsTotal`, `price`, `oldPrice`, `commission`, `deposit`, `agentName`, `agentPhone`, `cityName`, `images`, `offerId`, `deal` (Deal), `category` (Category).

Методы-форматтеры:
- `getPrice()` — форматированная цена (пробелы-разделители).
- `getOldPrice()` — форматированная старая цена (или null).
- `getCommission()` — форматированная комиссия (или null).
- `getDeposit()` — форматированный залог (или null).
- `getArea()` — форматированная площадь (1 знак после запятой, запятая-разделитель).
- `getKitchenArea()` — форматированная площадь кухни.
- `getRooms()` — количество комнат (или null если 0).
- `getFloor()` / `getFloorsTotal()` — этаж / этажей в доме (null если 0).
- `getFloorLine()` — строка "Этаж: X / Y" с обработкой null.
- `getDeal()` — label типа сделки ("Продажа", "Сдача" и т.д.).
- `getCategory()` — label категории ("Квартира", "Дом на земле" и т.д.).

### VkPostContextFactory

**Файл:** `app/Services/Vk/WallPost/VkPostContextFactory.php`

Создаёт `VkPostContext` из данных оффера.

- Загружает `Offer` по `offerId`.
- Получает агента, город.
- Находит предыдущий оффер по `code` (для `oldPrice`).
- Использует `offer->getPrice()` (с коррекцией для SALE < 1M).
- Defaults: `deal = SALE`, `category = APARTMENT`.

### VkPostGenerator

**Файл:** `app/Services/Vk/WallPost/VkPostGenerator.php`

Делегирует генерацию шаблону: `template->generate(context)`. Тонкая обёртка.

### VkPostTemplateInterface

**Файл:** `app/Interfaces/VkPostTemplateInterface.php`

Интерфейс: `generate(VkPostContext $context): string`.

### ScenarioVkPostTemplateResolver

**Файл:** `app/Helpers/ScenarioVkPostTemplateResolver.php`

Маппинг `ScenarioType` → класс шаблона:

| ScenarioType | Template |
|---|---|
| `ANNOUNCEMENT` | `AnnouncementTemplate` |
| `SALE` | `SaleTemplate` |
| `AGENT_CHANGED` | `SaleTemplate` |
| `PRICE_CHANGED` | `PriceChangedTemplate` |
| `BOOKING` | `BookedTemplate` |
| `SOLD` | `SoldTemplate` |
| `FEEDBACK` | `FeedbackTemplate` |

Для сценариев `WITHDRAWN`, `DELAYED`, `DELETED` — бросает Exception (эти сценарии не создают постов).

## Шаблоны

### AnnouncementTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/AnnouncementTemplate.php`

Текст анонса: "СКОРО В ПРОДАЖЕ КВАРТИРА". Цена "на согласовании". Призыв следить за новыми постами.

### SaleTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/SaleTemplate.php`

Текст продажи: "ПРОДАЕТСЯ {category}". Включает адрес, комнаты, площадь, этаж. Хештег `#broker_plus_post_{offerId}`.

### PriceChangedTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/PriceChangedTemplate.php`

Текст снижения цены: "ЦЕНА СНИЖЕНА!". Показывает старую цену, новую цену, выгоду.

### BookedTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/BookedTemplate.php`

Текст брони: "БРОНИРОВАНИЕ". Статус "забронировано".

### SoldTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/SoldTemplate.php`

Текст продажи: "ПРОДАНО".

### FeedbackTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/FeedbackTemplate.php`

Текст отзыва: "ОТЗЫВ о сделке". Благодарность клиентам.

### RentTemplate

**Файл:** `app/Services/Vk/WallPost/Templates/RentTemplate.php`

Текст аренды: "СДАЕТСЯ". Включает комиссию и залог. Хештег `#broker_plus_rent_{offerId}`.

> RentTemplate существует, но не используется в `ScenarioVkPostTemplateResolver` — нет сценария аренды.