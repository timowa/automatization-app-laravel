# Модуль VkApiService и FakeVkApiService

## Назначение

Обёртка над VK PHP SDK для выполнения вызовов к VK API. Управление токенами, загрузка медиа, постинг, репосты, товары, истории.

## VkApiService

**Файл:** `app/Services/Vk/VkApiService.php`

### Управление токенами

- `setToken(string $token): void` — устанавливает токен и создаёт новый `VKApiClient` с версией API из `config('vk.version', '5.199')` и языком `RUSSIAN`.
- `getClient(): VKApiClient` — возвращает текущий клиент.

### Методы

#### wallPost(int $ownerId, string $message, array $images = []): array

Создаёт пост на стене пользователя.

- Загружает изображения (до 10): `photos.getWallUploadServer` → `upload` → `photos.saveWallPhoto`.
- Формирует attachments из загруженных фото.
- Вызывает `wall.post`.
- Очищает временные файлы.
- Возвращает результат API (содержит `post_id`).

#### storiesPost(string $postId, string $imagePath): array

Создаёт историю с ссылкой на пост.

- `stories.getPhotoUploadServer` с параметрами `add_to_news=1`, `link_url` (на пост), `link_text='Смотреть'`.
- Загружает фото через `upload`.
- Вызывает `stories.save`.
- Возвращает результат (содержит `count`).

#### createReposts(int $userId, string $postId, array $groupIds): array

Пакетный репост в группы через VKScript `execute()`.

- Получает список групп пользователя через `groups.get` (filter=`moder`).
- Фильтрует группы, оставляя те, что в `$groupIds`.
- Выполняет VKScript: цикл по группам (до 25), вызывает `wall.repost` для каждой.
- Возвращает массив результатов [{group_id, response}].

#### createComment(int $ownerId, int $postId, string $message): array

Создаёт комментарий к посту через `wall.createComment`.

#### createProduct(int $groupId, string $name, string $description, int $price, int $categoryId, array $imagePaths): array

Создаёт товар в группе (market).

- Загружает изображения (до 5): `photos.getMarketUploadServer` → `upload` → `photos.saveMarketPhoto`.
- Вызывает `market.add` с `owner_id = -groupId`.
- Возвращает результат (содержит `market_item_id`).

#### editProduct(int $groupId, int $productId, string $name, string $description, int $price, int $categoryId): array

Редактирует товар через `market.edit`.

#### archiveProduct(int $groupId, int $productId): array

Удаляет/архивирует товар через `market.delete`.

#### getPostsStats(array $postIds): array

Получает статистику постов через `wall.getById`.

#### checkToken(): bool

Проверяет доступность токена через `users.get`. Возвращает true/false.

#### sendTokensMessage(string $text): void

Отправляет уведомление через `messages.send` в группу уведомлений. Токен и peer_id берутся из `config('vk.notify_group_token')` и `config('vk.notify_peer_id')`.

## FakeVkApiService

**Файл:** `app/Services/Vk/FakeVkApiService.php`

Наследник `VkApiService` для тестов. Заглушка, не делающая реальных API вызовов.

Возможности:
- `$calls` — массив всех вызовов с аргументами.
- `setFailNext(string $exceptionClass, string $message)` — следующий вызов бросит исключение.
- `reset()` — сброс состояния.
- Все методы (`wallPost`, `storiesPost`, `createReposts`, `createComment`, `createProduct`, `editProduct`, `archiveProduct`) возвращают фейковые данные (random ID, success=1, count=1).

## Rate limiting

- `usleep(350_000)` (≈350мс) между вызовами в циклах — не больше 3 запросов в секунду.
- `createReposts` использует VKScript `execute()` для пакетной обработки до 25 репостов за один API вызов.
- `ArchiveVkProductJob` использует VKScript для пакетного удаления товаров в одной группе.