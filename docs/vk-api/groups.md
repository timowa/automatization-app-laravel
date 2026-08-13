# Groups API Methods

Документация методов раздела Groups VK API.
Используются в: CreateVkRepostJob (получение списка групп пользователя для репостов).

Источник: https://dev.vk.com/ru/method/groups

---

## groups.get

Возвращает список сообществ указанного пользователя.

### Ключи доступа

- ключ доступа пользователя
- сервисный ключ доступа

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `user_id` | integer | Идентификатор пользователя, информацию о сообществах которого требуется получить. |
| `extended` | checkbox | 1 — полная информация о группах. По умолчанию 0. |
| `filter` | string | Фильтры сообществ через запятую. Возможные значения: admin, editor, moder, advertiser, groups, publics, events, hasAddress. По умолчанию — все сообщества. |
| `fields` | string | Дополнительные поля (только при extended=1). Возможные значения: activity, can_create_topic, can_post, can_see_all_posts, city, contacts, counters, country, description, finish_date, fixed_post, links, members_count, place, site, start_date, status, verified, wiki_page. |
| `offset` | positive | Смещение для выборки подмножества сообществ. |
| `count` | positive | Количество сообществ. Максимально — 1000. |

#### Описание фильтров

- `admin` — сообщества, где пользователь является администратором
- `editor` — администратором или редактором
- `moder` — администратором, редактором или модератором
- `advertiser` — рекламодателем
- `groups` — группы
- `publics` — публичные страницы
- `events` — события
- `hasAddress` — сообщества с указанными адресами

> **Использование в проекте:** CreateVkRepostJob вызывает `groups.get` с фильтром `moder`
> для получения групп, в которых у агента есть права модератора (для репостов).

### Результат

Возвращает объект:
- `count` (integer) — число результатов
- `items` (array) — массив идентификаторов сообществ (при extended=0) или массив объектов (при extended=1)

### Коды ошибок

| Код | Описание |
|-----|----------|
| 260 | Access to the groups list is denied due to the user's privacy settings |
| 717 | Group list is not obsolete |