# Модуль шаблонов и генерации историй

## Назначение

Генерация баннера-изображения для VK-истории (story) на основе данных оффера, загрузка через VK API.

## Архитектура

```
VkStoriesContextFactory → VkStoriesContext
                              ↓
Шаблон (VkStoriesTemplateInterface) → getPrice(), getDetails()
                              ↓
VkStoriesGenerator::generate(context, template) → путь к JPG-файлу
                              ↓
vkApi->storiesPost(postId, imagePath)
```

## Компоненты

### VkStoriesContext

**Файл:** `app/Services/Vk/Stories/VkStoriesContext.php`

Контекст генерации истории.

Поля: `agentName`, `price`, `commission`, `deposit`, `address`, `rooms`, `image` (URL), `deal` (Deal), `category` (Category), `postId`, `offerId`.

Методы-форматтеры: `getPrice()`, `getCommission()`, `getDeposit()`, `getRooms()`, `getDeal()`, `getCategory()`.

### VkStoriesContextFactory

**Файл:** `app/Services/Vk/Stories/VkStoriesContextFactory.php`

Создаёт `VkStoriesContext` из данных поста и оффера.

- Загружает `VkWallPost` по `postId`.
- Получает оффер и агента через связи.
- Первое изображение из `offer->images` как фон.
- Defaults: `deal = SALE`, `category = APARTMENT`.

### VkStoriesGenerator

**Файл:** `app/Services/Vk/Stories/VkStoriesGenerator.php`

Генерирует JPG-баннер для истории через Intervention Image.

Ресурсы:
- Шаблон-фон: `storage/app/assets/images/vkstory.png` (1080×1920).
- Шрифт обычный: `storage/app/assets/fonts/ProximaNova.ttf`.
- Шрифт жирный: `storage/app/assets/fonts/ProximaNovaSemibold.ttf`.

Процесс:
1. Загружает шаблон-фон.
2. Рисует красный прямоугольник (#ce1b21) для цены (y=400, высота 150).
3. Вписывает цену жирным шрифтом (90px, белый).
4. Вписывает детали (адрес, комнаты, категория) жирным шрифтом (60px, тёмно-серый) построчно.
5. Скачивает первое изображение оффера, обрезает через `cover(900, 600)`, вставляет сверху.
6. Сохраняет результат как `storage/app/tmp/story_{uniqid}.jpg`.
7. Возвращает путь к файлу.

Вызывающий Job ответственен за удаление временного файла после загрузки.

### VkStoriesTemplateInterface

**Файл:** `app/Interfaces/VkStoriesTemplateInterface.php`

Интерфейс:
- `getPrice(VkStoriesContext $context): string` — текст цены.
- `getDetails(VkStoriesContext $context): string` — детали (многострочный текст, разделитель `\n`).

### Шаблоны историй

#### SaleStoriesTemplate

**Файл:** `app/Services/Vk/Stories/Templates/SaleStoriesTemplate.php`

- Цена: `{price} руб.`
- Детали: "Продажа или Обмен: {category}", адрес, "Комнат: N".

#### RentStoriesTemplate

**Файл:** `app/Services/Vk/Stories/Templates/RentStoriesTemplate.php`

- Цена: `{price} руб./мес.`
- Детали: "Сдача: {category}", адрес, "Комнат: N".

### Выбор шаблона

Шаблон выбирается по типу сделки (`Deal`):
- `SALE` → `SaleStoriesTemplate`
- `RENT_OUT` → `RentStoriesTemplate`
- Иначе — RuntimeException.

Резолвция дублируется в трёх местах:
- `CreateVkStoriesJob::resolveTemplate()`
- `CreateVkLoopStoryJob::resolveTemplate()`
- `PublishLoopStoriesCommand::resolveTemplate()`