# Модуль админ-панели

## Назначение

Веб-панель управления агентами и их VK-токенами. Авторизация по паролю, защита маршрутов middleware.

## Контроллеры

### AuthController

**Файл:** `app/Http/Controllers/AuthController.php`

Авторизация администратора.

- `loginForm(?string $error)` — форма входа (view `login`).
- `login(Request)` — проверка логина/пароля. Логин и хеш пароля из `config('app.admin_login')` и `config('app.admin_password_hash')`. При успехе — `session(['is_admin' => true])`, редирект на `/agents`.
- `logout()` — очистка сессии, редирект на `/login`.

### AgentController

**Файл:** `app/Http/Controllers/AgentController.php`

CRUD агентов и управление токенами.

- `list()` — список агентов (view `agents-list`).
- `create()` — форма создания (view `agent-form`).
- `store(StoreAgentRequest)` — создание агента. Нормализует телефон (только цифры). Логирует.
- `edit(int $id)` — форма редактирования (view `agent-form`).
- `save(UpdateAgentRequest, int $id)` — обновление агента. После обновления синхронизирует VK-профиль через `SyncVkUserAction`.
- `delete(Request, int $id)` — удаление с проверкой пароля (config `app.rudenko_password`).
- `changeToken(int $id)` — форма обновления токена (view `agent-token`).
- `updateToken(Request, int $id)` — парсит ссылку авторизации VK (extract access_token, user_id из URL fragment), создаёт/обновляет `VkUser`.

Ответы `store`, `save`, `delete`, `updateToken` — через `viewJson()` хелпер (JSON с success/messages/redirect).

## Middleware

### AdminAuthMiddleware

**Файл:** `app/Http/Middleware/AdminAuthMiddleware.php`

Проверяет `session('is_admin')`. Если нет — редирект на `/login`.

### ApiKeyMiddleware

**Файл:** `app/Http/Middleware/ApiKeyMiddleware.php`

Защита API-эндпоинта `/offer`. Проверяет Bearer-токен в заголовке `Authorization`. Токен сравнивается с `config('app.api_key')` через `hash_equals()` (защита от timing-атак).

## Form Requests

### StoreAgentRequest

**Файл:** `app/Http/Requests/StoreAgentRequest.php`

Валидация создания агента: `name` (min:5), `phone` (regex `/^7\d{10}$/`, unique).

### UpdateAgentRequest

**Файл:** `app/Http/Requests/UpdateAgentRequest.php`

Валидация обновления агента: аналогично StoreAgentRequest, но unique с ignore текущего ID.