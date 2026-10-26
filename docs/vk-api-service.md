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

HTTP-клиент SDK собирается с `http_errors = false` и `VkApiResponseGuard`: ответ не 200 и ошибка без строкового `error_msg` пишутся в канал `vk` (код и тело без токена). Пустой `error_msg` заменяется на пустую строку, чтобы SDK вернул `VKApiUnknownException` с кодом 1, а не `TypeError`. Ответ `Captcha needed` (код 14) пишется отдельной строкой: метод, `captcha_sid`, картинка и адрес страницы капчи без параметров. Тело для SDK сохраняет `captcha_sid`, картинку и `redirect_uri`, чтобы исключение осталось `VKApiCaptchaException`. Если текста ошибки нет, подставляется `Captcha needed`. Повторы кода 1 и HTTP 405 делает `VkStoryPoster`, не этот метод.

#### createReposts(int $userId, string $postId, array $groupIds): array

Пакетный репост в группы через VKScript `execute()`.

- Получает список групп пользователя через `groups.get` (filter=`moder`).
- Фильтрует группы, оставляя те, что в `$groupIds`.
- Выполняет VKScript: цикл по группам (до 25), вызывает `wall.repost` для каждой.
- Возвращает массив результатов [{group_id, response}].

#### createComment(int $ownerId, int $postId, string $message): array

Создаёт комментарий к посту через `wall.createComment`.

#### uploadMarketPhoto(int $groupId, array $imagePaths): array

Загружает до 5 фото товара. Битый файл пропускается. Если в ответе сохранения нет `response`, метод возвращает пустой список и не читает отсутствующий ключ. Flood control пробрасывается вызывающему.

#### createProductsBatch(int $groupId, string $name, string $description, int $price, int $categoryId, array $photoIds): array

Создаёт товар в группе (`market.add`, `owner_id = -groupId`).

Без числового главного фото (`photoIds[0]`) метод бросает `RuntimeException` «Нет главного фото товара» и не вызывает `market.add`.

#### editProduct(int $groupId, int $productId, string $name, string $description, int $price, int $categoryId): array

Редактирует товар через `market.edit`.

#### archiveProduct(int $groupId, int $productId): array

Удаляет/архивирует товар через `market.delete`.

#### getPostsStats(array $postIds): array

Получает статистику постов через `wall.getById`.

#### checkToken(): bool

Проверяет доступность токена через `users.get`. Возвращает true/false.

#### sendTechMessage(string $text): void

Отправляет техническое уведомление через `messages.send` в группу уведомлений. Токен и peer_ids берутся из `config('vk.notify_group_token')` и `config('vk.notify_peer_ids')`.

#### getFriendRequests(): array

Входящие заявки в друзья (`friends.getRequests`, extended). Используется синхронизацией автоприветствия.

#### hasOutgoingMessageWithUser(int $userId): bool

Проверяет, писал ли агент пользователю (`messages.getHistory`).

#### uploadMessagesPhoto(string $absolutePath): int

Загрузка фото для вложения в ЛС: upload server → save → возвращает `media_id` (без `owner_id` в строке; owner берётся из `vk_users.vk_user_id`).

#### sendDirectMessage(int $userId, string $message, ?string $attachment = null): mixed

Личное сообщение (`messages.send`), опционально с attachment вида `photo{owner}_{media}`.

#### sendWelcomeBatch(array $messages): array

Не больше `VkApiService::WELCOME_BATCH_LIMIT` (сейчас 3, технический потолок 12) приветствий одним `execute`. Вызов тот же, что у `createReposts`: POST-тело, не адрес. Потолок 12 — это `floor(25 / 2)`: на заявку уходит `messages.getHistory` и `messages.send`, внутри `execute` не больше 25 вызовов. Скрипт для каждой заявки смотрит `messages.getHistory`: если `count > 0`, письмо не шлёт. Иначе вызывает `messages.send`. Ответ — список `{user_id, message_id, error}` той же длины, что и переданный в скрипт вход (лишнее обрезается). Наружу возвращается поле `response`.

Подробнее: [friend-request-welcome.md](friend-request-welcome.md).

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