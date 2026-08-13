# Модуль моделей Eloquent

## Назначение

Модели Eloquent для работы с таблицами БД. Используются для связей и casts; для выборок предпочтителен класс DB.

## Модели

### Agent

**Файл:** `app/Models/Agent.php`

Таблица: `agents`. Timestamps: только `updated_at` (нет `created_at`).

Fillable: `name`, `phone`.

Связи:
- `vkUser()` — HasOne `VkUser`.
- `offers()` — HasMany `Offer`.

### Offer

**Файл:** `app/Models/Offer.php`

Таблица: `offers`. Timestamps: только `created_at` (нет `updated_at`).

Fillable: `offer_id`, `code`, `stage`, `status`, `price`, `area`, `kitchen_area`, `living_area`, `city`, `location`, `agent_id`, `images`, `deal`, `category`, `rooms`, `rooms_offered`, `floor`, `floors_total`, `commission`, `deposit`.

Casts: `images` → array, `location` → array, площади → float, числа → integer, `created_at` → datetime.

Enum-методы: `city()`, `deal()`, `category()`, `status()` — возвращают enum из значения БД.

Связи:
- `agent()` — BelongsTo `Agent`.
- `vkWallPosts()` — HasMany `VkWallPost`.
- `publication()` — HasOne `Publication`.

Спецметоды:
- `getPrice()` — цена с коррекцией (SALE < 1M → ×100).
- `getBasePrice()` — сырая цена.
- `getAddressFromLocation()` — строка "город, адрес" из JSON-поля location.

### Publication

**Файл:** `app/Models/Publication.php`

Таблица: `publications`.

Fillable: `offer_id`, `scenario`.

Cast: `scenario` → `ScenarioType` enum.

Связи:
- `tasks()` — HasMany `PublicationTask`.
- `offer()` — BelongsTo `Offer`.

### PublicationTask

**Файл:** `app/Models/PublicationTask.php`

Таблица: `publication_tasks`.

Fillable: `publication_id`, `type`, `status`, `error`, `external_id`, `dependent_task_id`.

Casts: `type` → `PublicationTaskType`, `status` → `PublicationTaskStatus`.

Связи:
- `publication()` — BelongsTo `Publication`.
- `offer()` — HasOneThrough `Offer` через `Publication`.
- `parentTask()` — BelongsTo `PublicationTask` (через `dependent_task_id`).

### VkUser

**Файл:** `app/Models/VkUser.php`

Таблица: `vk_users`. Без timestamps.

`$hidden = ['vk_token']` — токен никогда не сериализуется.

Fillable: `agent_id`, `vk_user_id`, `vk_token`, `email`, `is_token_available`, и множество полей профиля (first_name, last_name, screen_name, domain, bdate, relation, city, и т.д.), `raw` (полный ответ API).

Casts: булевые поля, числа, `raw` → array.

Методы:
- `agent()` — BelongsTo `Agent`.
- `normalizeBdate(?string $bdate)` — нормализация формата даты (DD.MM.YYYY → YYYY-MM-DD).
- `getToken()` — возвращает `vk_token` или пустую строку.
- `getAgentId()` — int `agent_id`.

### VkWallPost

**Файл:** `app/Models/VkWallPost.php`

Таблица: `vk_posts`. Timestamp: `posted_at` (нет `updated_at`).

Fillable: `offer_id`, `post_id`, `owner_id`, `task_id`.

Связи:
- `offer()` — BelongsTo `Offer`.

Методы:
- `getFullId()` — возвращает `"{owner_id}_{post_id}"` (формат VK).

### VkGroup

**Файл:** `app/Models/VkGroup.php`

Таблица: `vk_groups`. Без timestamps.

Fillable: `group_id` (int), `city` (int).

### VkProduct

**Файл:** `app/Models/VkProduct.php`

Таблица: `vk_products`. С timestamps.

Fillable: `offer_id`, `agent_id`, `group_id`, `product_id`, `task_id`, `is_archived`.

Casts: `group_id` → int, `product_id` → int, `is_archived` → bool.

Связи:
- `offer()` — BelongsTo `Offer`.
- `agent()` — BelongsTo `Agent`.
- `task()` — BelongsTo `PublicationTask`.

### VkLoopStory

**Файл:** `app/Models/VkLoopStory.php`

Таблица: `vk_loop_stories`. С timestamps.

Fillable: `offer_id`, `task_id`, `is_active`, `last_published_at`.

Casts: `is_active` → bool, `last_published_at` → datetime.

Связи:
- `offer()` — BelongsTo `Offer`.
- `task()` — BelongsTo `PublicationTask`.

### VkPostStat

**Файл:** `app/Models/VkPostStat.php`

Таблица: `vk_post_stats`. Timestamp: `datetime` (нет `updated_at`).

Fillable: `vk_post_id`, `views`.

Cast: `datetime` → datetime.

Связи:
- `vkWallPost()` — BelongsTo `VkWallPost`.

> Поле `is_active` в таблице `offers` — используется в `GetStatsCommand` для фильтрации активных постов, но не объявлено в fillable модели `Offer`. Добавляется на уровне БД.

## Диаграмма связей

```
Agent ──< Offer ──< VkWallPost
   │       │         
   └─VkUser │         
           │         
       Publication ──< PublicationTask
           │                │
           │                └──> VkProduct
           │                └──> VkLoopStory
           │
       VkGroup (справочник групп)
       
VkWallPost ──< VkPostStat
```