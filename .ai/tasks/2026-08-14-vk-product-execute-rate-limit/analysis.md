# Analysis

## Task overview

Рефакторинг CreateVkProductJob и VkApiService::createProduct для:
1. Использования execute (VKScript) для группировки вызовов market.add
2. Решения проблемы с устаревшими методами photos.getMarketUploadServer / photos.saveMarketPhoto
3. Соблюдения лимита VK API — не более 3 запросов в секунду

## Current architecture

### CreateVkProductJob

Цикл по группам (`VkGroup::all()`), для каждой группы вызывается `VkApiService::createProduct()`.

Внутри `createProduct()` для каждой группы выполняется:
1. `photos.getMarketUploadServer` — получение upload URL (1 API-запрос)
2. Загрузка фото на upload URL (HTTP POST, не API-запрос, но сетевой запрос)
3. `photos.saveMarketPhoto` — сохранение фото (1 API-запрос на каждое фото, до 5 шт.)
4. `market.add` — создание товара (1 API-запрос)

Итого на одну группу: 2 + N (где N = количество фото, 1-5) = 3-6 API-запросов.
Для 11 групп: 33-66 API-запросов.

Между группами: `usleep(350_000)` = 350мс.
При 4 запросах на группу и 350мс паузе: ~11 запросов/сек — превышает лимит в 3/сек.

### Дополнительная проблема

`photos.getMarketUploadServer` и `photos.saveMarketPhoto` удалены из VK API документации.
В логах: "Unknown method passed" для 9 из 11 групп. Для последних 2 групп: "Too many requests per second".

## Affected areas

- `app/Jobs/CreateVkProductJob.php` — основная логика цикла по группам
- `app/Services/Vk/VkApiService.php` — метод createProduct(), editProduct(), archiveProduct()
- `app/Jobs/EditVkProductJob.php` — цикл по продуктам с usleep
- `app/Jobs/ArchiveVkProductJob.php` — уже использует execute, но только для случая 1 группы
- `docs/vk-api/photos.md` — документация по устаревшим методам
- `docs/vk-api/market.md` — документация по market.add/edit/delete

## Existing implementation

### Уже использует execute:
- `CreateVkRepostJob` → `VkApiService::createReposts()` — пакетный репост до 25 групп через VKScript
- `ArchiveVkProductJob` — пакетное удаление товаров через execute, но ТОЛЬКО когда все товары в одной группе. Если группы разные — цикл с usleep.

### Не использует execute:
- `CreateVkProductJob` — цикл с usleep(350_000)
- `EditVkProductJob` — цикл с usleep(350_000)

## Architecture decisions

### Решение 1: Разделить загрузку фото и создание товара

Фото товара загружается ОДИН раз (т.к. товар один и тот же для всех групп).
Затем market.add для всех групп выполняется одним execute.

Поток:
1. Загрузить фото товара ОДИН раз → получить photo_id
2. Собрать VKScript с market.add для каждой группы (до 25 за один execute)
3. Выполнить execute
4. Сохранить результаты в vk_products

Проблема: photos.getMarketUploadServer / photos.saveMarketPhoto устарели.
Альтернатива: загрузить фото через photos.getWallUploadServer + photos.saveWallPhoto (рабочие методы) и использовать полученные photo_id в market.add.

> Требуется проверка: принимает ли market.add photo_id от wall-фото, а не от market-фото.
> Если нет — нужен другой подход. Возможно market.add без фото (main_photo_id=null).

### Решение 2: execute для market.add

VKScript для создания товаров в нескольких группах:

```javascript
var groups = [groupId1, groupId2, ...]; // до 25
var name = "...";
var description = "...";
var price = 123;
var categoryId = 1;
var mainPhotoId = 123;
var photoIds = "456,789";

var result = [];
var i = 0;
while (i < groups.length) {
    var groupId = groups[i];
    result.push({
        "group_id": groupId,
        "response": API.market.add({
            "owner_id": -groupId,
            "name": name,
            "description": description,
            "category_id": categoryId,
            "price": price,
            "main_photo_id": mainPhotoId,
            "photo_ids": photoIds
        })
    });
    i = i + 1;
}
return result;
```

Один execute = 1 HTTP-запрос, внутри до 25 вызовов market.add.

### Решение 3: Rate limit для остальных запросов

Загрузка фото (getWallUploadServer + upload + saveWallPhoto) = 3 API-запроса.
Если групп больше 25 — разбить на несколько execute (25 групп на execute, пауза между execute).

Общее количество API-запросов при новом подходе:
- 1-3 запроса на загрузку фото (один раз)
- 1 execute на каждые 25 групп
- Итого для 11 групп: 3 + 1 = 4 API-запроса (вместо 44+)

### Решение 4: EditVkProductJob — тоже execute

EditVkProductJob перебирает товары в цикле с usleep. market.edit можно сгруппировать в execute.
Все товары имеют одинаковые name/description/price/categoryId (берутся из одного offer),
различаются только group_id и product_id.

```javascript
var products = [
    {"group_id": 123, "product_id": 456},
    {"group_id": 789, "product_id": 012}
];
var name = "...";
var description = "...";
var price = 123;
var categoryId = 1;

var result = [];
var i = 0;
while (i < products.length) {
    result.push(API.market.edit({
        "owner_id": -products[i].group_id,
        "item_id": products[i].product_id,
        "name": name,
        "description": description,
        "category_id": categoryId,
        "price": price
    }));
    i = i + 1;
}
return result;
```

### Решение 5: ArchiveVkProductJob — execute для разных групп

Текущая реализация уже использует execute, но только если все товары в одной группе.
Если товары в разных группах — цикл с usleep. Нужно объединить оба случая в один execute.

## Dependencies

```
Backend:
- Рефакторинг VkApiService::createProduct() — разделить загрузку фото и market.add
- Новый метод VkApiService::createProductsBatch() — execute с VKScript
- Рефакторинг VkApiService::editProduct() → editProductsBatch() — execute
- Рефакторинг VkApiService::archiveProduct() → archiveProductsBatch() — execute для любых групп
- Рефакторинг CreateVkProductJob — загрузка фото один раз, затем execute
- Рефакторинг EditVkProductJob — execute вместо цикла
- Рефакторинг ArchiveVkProductJob — execute для любых случаев

Execution order:
1. Backend (VkApiService + Jobs)
2. Review (проверка rate limit, проверка execute)
```

## Risks

1. **market.add и photo_id от wall-фото** — неизвестно, принимает ли market.add photo_id,
   полученный через photos.saveWallPhoto, а не photos.saveMarketPhoto. Если нет — товар
   будет создан без фото или с ошибкой. Требует проверки.

2. **Устаревшие методы** — photos.getMarketUploadServer / photos.saveMarketPhoto возвращают
   "Unknown method passed". Нужно найти рабочий способ загрузки фото для товара.
   Варианты:
   - photos.getWallUploadServer + photos.saveWallPhoto (нужно проверить совместимость)
   - Загрузка без фото (market.add с main_photo_id=null)
   - Другой метод загрузки

3. **Лимит execute** — до 25 вызовов API в одном execute. Если групп больше 25 —
   нужно разбивать на несколько execute с паузой.

4. **Идемпотентность** — если execute выполнился частично (часть товаров создана, часть нет),
   нужно корректно сохранить только успешные результаты.

5. **Параллельные jobs** — если queue worker запускает несколько jobs одновременно,
   они могут превысить rate limit вместе. TaskDispatcher dispatch-ит все pending задачи
   одновременно. Это отдельная проблема — возможная доработка в будущем (rate limiter на уровне очереди).

## Questions

1. Можно ли загружать фото для товара через photos.getWallUploadServer + photos.saveWallPhoto?
   Или нужен другой метод? (photos.getMarketUploadServer удалён из API)

2. Допустимо ли создавать товар без фото (main_photo_id=null) как временное решение,
   пока не найдён рабочий метод загрузки фото для товара?

3. Нужно ли применять execute ко всем jobs (wall.post, stories, comment) или только к
   product-related jobs? Сейчас wall.post, stories, comment — по 1 вызову на job,
   они не превышают лимит по отдельности, но при одновременном выполнении нескольких
   jobs могут превысить.

## Definition of Done

- [ ] CreateVkProductJob использует execute для создания товаров в нескольких группах
- [ ] EditVkProductJob использует execute для редактирования товаров
- [ ] ArchiveVkProductJob использует execute для всех случаев (не только 1 группа)
- [ ] Фото товара загружается один раз, не для каждой группы
- [ ] Устранена ошибка "Unknown method passed" (устаревшие методы заменены)
- [ ] Устранена ошибка "Too many requests per second" при создании товаров
- [ ] Количество API-запросов при создании товаров в 11 группах — не более 5 (вместо 44+)
- [ ] Лимит 3 запросов/сек соблюдается
- [ ] Результаты execute корректно сохраняются в vk_products
- [ ] Добавлены тесты