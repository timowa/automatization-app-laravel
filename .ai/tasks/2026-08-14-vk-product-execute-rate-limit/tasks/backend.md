# Backend: Рефакторинг CreateVkProductJob — execute и rate limit

## Цель

Перевести создание/редактирование/архивацию товаров VK на execute (VKScript),
устранить превышение rate limit (не более 3 запросов/сек), заменить устаревшие
методы photos.getMarketUploadServer / photos.saveMarketPhoto.

## Контекст

VK API лимит — не более 3 запросов в секунду. Текущая реализация CreateVkProductJob
делает ~44 API-запроса для 11 групп (4 запроса × 11 групп), превышая лимит.

Методы photos.getMarketUploadServer и photos.saveMarketPhoto удалены из VK API
и возвращают "Unknown method passed".

execute позволяет выполнить до 25 API-вызовов в одном HTTP-запросе.

## Существующая реализация

### VkApiService::createProduct() (app/Services/Vk/VkApiService.php:217-272)

Для каждой группы выполняет:
1. `photos.getMarketUploadServer` — 1 запрос (УСТАРЕЛ, "Unknown method passed")
2. upload фото на сервер — HTTP POST (не API-запрос)
3. `photos.saveMarketPhoto` — 1 запрос на фото (УСТАРЕЛ)
4. `market.add` — 1 запрос

### CreateVkProductJob (app/Jobs/CreateVkProductJob.php:85-114)

Цикл foreach по VkGroup::all(), для каждой группы вызывается createProduct().
usleep(350_000) между итерациями.

### EditVkProductJob (app/Jobs/EditVkProductJob.php:85-96)

Цикл foreach по VkProduct, для каждого вызывается editProduct().
usleep(350_000) между итерациями.

### ArchiveVkProductJob (app/Jobs/ArchiveVkProductJob.php:91-118)

Уже использует execute для случая 1 группы (несколько товаров в одной группе).
Для разных групп — цикл с usleep.

### Пример execute в проекте

VkApiService::createReposts() — собирает VKScript с wall.repost для до 25 групп,
выполняет одним execute. Использовать как образец.

## Необходимые изменения

### 1. VkApiService — новые методы

#### uploadMarketPhoto(array $imagePaths): array

Загружает фото товара ОДИН раз. Возвращает массив photo_id.

Поскольку photos.getMarketUploadServer / photos.saveMarketPhoto устарели,
нужно использовать рабочий метод. Варианты:
- photos.getWallUploadServer + photos.saveWallPhoto (проверить совместимость с market.add)
- Если не работает — market.add без фото (main_photo_id=null, photo_ids="")

Псевдокод:
```
uploadMarketPhoto(array $imagePaths): array
    // Загрузить фото через рабочий метод
    // photos.getWallUploadServer → upload → photos.saveWallPhoto
    // Вернуть массив [photo_id, ...]
```

#### createProductsBatch(int $groupId, string $name, string $description, int $price, int $categoryId, array $photoIds): array

Создаёт товары в нескольких группах одним execute.

Псевдокод:
```
createProductsBatch(array $groupIds, string $name, string $description, int $price, int $categoryId, array $photoIds): array
    groupIdsJson = json_encode($groupIds)
    mainPhotoId = $photoIds[0] ?? null
    photoIdsStr = implode(',', array_slice($photoIds, 1))

    code = VKScript:
        var groups = {$groupIdsJson};
        var name = "{$name}";
        var description = "{$description}";
        var price = {$price};
        var categoryId = {$categoryId};
        var mainPhotoId = {$mainPhotoId};
        var photoIds = "{$photoIdsStr}";
        var result = [];
        var i = 0;
        while (i < groups.length && i < 25) {
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

    return execute(code)
```

#### editProductsBatch(array $products, string $name, string $description, int $price, int $categoryId): array

$products = [['group_id' => X, 'product_id' => Y], ...]

Псевдокод:
```
editProductsBatch(array $products, string $name, ...): array
    productsJson = json_encode($products)

    code = VKScript:
        var products = {$productsJson};
        var name = "{$name}";
        ...
        var result = [];
        var i = 0;
        while (i < products.length && i < 25) {
            result.push(API.market.edit({
                "owner_id": -products[i].group_id,
                "item_id": products[i].product_id,
                "name": name,
                ...
            }));
            i = i + 1;
        }
        return result;

    return execute(code)
```

#### archiveProductsBatch(array $products): array

Объединить текущую логику ArchiveVkProductJob в один execute для ЛЮБЫХ групп.

Псевдокод:
```
archiveProductsBatch(array $products): array
    productsJson = json_encode($products)

    code = VKScript:
        var products = {$productsJson};
        var result = [];
        var i = 0;
        while (i < products.length && i < 25) {
            result.push(API.market.delete({
                "owner_id": -products[i].group_id,
                "item_id": products[i].product_id
            }));
            i = i + 1;
        }
        return result;

    return execute(code)
```

### 2. CreateVkProductJob — рефакторинг

Заменить цикл на:
1. Загрузить фото один раз: `$photoIds = $vkApi->uploadMarketPhoto($context->images)`
2. Разбить группы на чанки по 25
3. Для каждого чанка: `$results = $vkApi->createProductsBatch($chunk, $name, $description, $price, $categoryId, $photoIds)`
4. Обработать результаты, сохранить VkProduct для успешных
5. usleep(350_000) между чанками (если групп > 25)

### 3. EditVkProductJob — рефакторинг

Заменить цикл на:
1. Разбить продукты на чанки по 25
2. Для каждого чанка: `$vkApi->editProductsBatch($chunk, $name, $description, $price, $categoryId)`
3. usleep между чанками

### 4. ArchiveVkProductJob — рефакторинг

Заменить всю логику на:
1. Разбить продукты на чанки по 25
2. Для каждого чанка: `$vkApi->archiveProductsBatch($chunk)`
3. usleep между чанками
4. Удалить отдельную ветку для count == 1

## Затрагиваемые файлы

- `app/Services/Vk/VkApiService.php` — новые методы, удаление старых
- `app/Jobs/CreateVkProductJob.php` — рефакторинг цикла
- `app/Jobs/EditVkProductJob.php` — рефакторинг цикла
- `app/Jobs/ArchiveVkProductJob.php` — рефакторинг, единый execute

## API изменения

Нет новых endpoint. Изменяется только внутреннее использование VK API:
- photos.getMarketUploadServer → photos.getWallUploadServer (или другой рабочий метод)
- photos.saveMarketPhoto → photos.saveWallPhoto (или другой рабочий метод)
- market.add — через execute вместо отдельных вызовов
- market.edit — через execute вместо отдельных вызовов
- market.delete — через execute для всех случаев

## Модели

Без изменений. VkProduct используется как есть.

## Миграции

Нет.

## Тесты

- Тест createProductsBatch: мок execute, проверка VKScript кода
- Тест editProductsBatch: мок execute, проверка VKScript кода
- Тест archiveProductsBatch: мок execute, проверка VKScript кода
- Тест CreateVkProductJob: проверка что фото загружается один раз
- Тест EditVkProductJob: проверка execute вместо цикла
- Тест ArchiveVkProductJob: проверка execute для разных групп
- Тест chunking: проверка разбивки на чанки по 25

## Ограничения

- execute — до 25 API-вызовов за один запрос
- Если групп больше 25 — разбивать на несколько execute с паузой
- VKScript не поддерживает const, let, function, Promise, async/await
- Все строковые значения в VKScript должны быть экранированы
- Идемпотентность: сохранять только успешные результаты execute

## Out of scope

- Rate limiter на уровне очереди (для параллельных jobs) — отдельная задача
- Рефакторинг других jobs (wall.post, stories, comment) — они делают 1 API-запрос
- Реализация новых методов загрузки фото для market — если wall-фото не работает
  с market.add, это требует исследования и возможно отдельной задачи

## Definition of Done

- [ ] VkApiService::uploadMarketPhoto() загружает фото один раз рабочим методом
- [ ] VkApiService::createProductsBatch() создаёт товары через execute
- [ ] VkApiService::editProductsBatch() редактирует товары через execute
- [ ] VkApiService::archiveProductsBatch() архивирует товары через execute
- [ ] CreateVkProductJob загружает фото один раз, затем execute
- [ ] EditVkProductJob использует execute вместо цикла
- [ ] ArchiveVkProductJob использует execute для всех случаев
- [ ] Ошибка "Unknown method passed" устранена
- [ ] Ошибка "Too many requests per second" устранена
- [ ] Для 11 групп: не более 5 API-запросов (3 на фото + 1 execute)
- [ ] Группы > 25 разбиваются на чанки с паузой
- [ ] Результаты execute корректно сохраняются в vk_products
- [ ] Добавлены тесты