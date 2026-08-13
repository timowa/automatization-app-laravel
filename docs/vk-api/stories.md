# Stories API Methods

Документация методов раздела Stories VK API.
Используются в: CreateVkStoriesJob, CreateVkLoopStoryJob.

Источник: https://dev.vk.com/ru/method/stories

---

## stories.getPhotoUploadServer

Метод получает адрес сервера для загрузки изображения в историю.

### Ключи доступа

- ключ доступа пользователя, полученный в Standalone-приложении через Implicit Flow (требуется право доступа: stories)
- ключ доступа сообщества (требуются права доступа: stories)

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `add_to_news` | checkbox | 1 — разместить историю в новостях. 0 — не размещать (по умолчанию). Обязательный, если не передан user_ids. |
| `user_ids` | string | Идентификаторы пользователей, которые будут видеть историю (для отправки в личном сообщении), через запятую. Обязательный, если не передан add_to_news. |
| `reply_to_story` | string | Идентификатор истории, в ответ на которую создаётся новая. |
| `link_text` | string | Текст ссылки для перехода из истории (только для сообществ). Возможные значения: to_store, vote, more, book, order, enroll, fill, signup, buy, ticket, write, open, learn_more (по умолчанию), view, go_to, contact, watch, play, install, read. |
| `link_url` | string | Адрес ссылки для перехода из истории. Макс. длина = 2048. |
| `group_id` | integer | Идентификатор сообщества, в которое должна быть загружена история (при работе с ключом доступа пользователя). |
| `clickable_stickers` | text | Объект кликабельного стикера. |

### Результат

| Поле | Тип | Описание |
|------|-----|----------|
| `upload_url` | string | Адрес, по которому нужно загрузить изображение. |
| `user_ids` | array[integer] | Массив идентификаторов пользователей, которые будут видеть историю. |
| `peer_ids` | array[integer] | Массив идентификаторов пользователей или сообществ, которые будут видеть видеоисторию. |

### Пример ответа

```json
{
  "response": {
    "upload_url": "https://pu.vk.com/c857012/ss2159/upload.php?_query=eyJ0aW...wIn0",
    "user_ids": [],
    "peer_ids": []
  }
}
```

### Коды ошибок

| Код | Описание |
|-----|----------|
| 19 | Content blocked |
| 900 | Can't send messages for users from blacklist |
| 1602 | Incorrect reply privacy |

---

## stories.save

Метод сохраняет историю в профиле после её успешной загрузки на сервер.

### Ключи доступа

- ключ доступа пользователя, полученный в Standalone-приложении через Implicit Flow (требуется право доступа: stories)
- ключ доступа сообщества (требуются права доступа: stories)

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `upload_results` | string | Обязательный. История в формате multipart/form-data. Параметр возвращается в результате загрузки истории на сервер. |
| `upload_results_json` | text | |
| `extended` | checkbox | |
| `fields` | string | |

### Результат

| Поле | Тип | Описание |
|------|-----|----------|
| `count` | integer | Количество загруженных историй. |
| `items` | array[object] | Массив объектов историй. |

### Пример ответа

```json
{
  "response": {
    "count": 1,
    "items": [
      {
        "id": 456239020,
        "owner_id": 743784474,
        "access_key": "story",
        "can_comment": 0,
        "can_reply": 0,
        "can_see": 1,
        "can_like": false,
        "can_share": 0,
        "can_hide": 1,
        "date": 1674040085,
        "expires_at": 1674126485,
        "photo": {
          "album_id": -81,
          "date": 1674039436,
          "id": 457239029,
          "owner_id": 743784474,
          "sizes": [
            {"height": 50, "type": "s", "width": 75, "url": "..."},
            {"height": 87, "type": "m", "width": 130, "url": "..."},
            {"height": 170, "type": "j", "width": 256, "url": "..."},
            {"height": 402, "type": "x", "width": 604, "url": "..."},
            {"height": 537, "type": "y", "width": 807, "url": "..."},
            {"height": 852, "type": "z", "width": 1280, "url": "..."},
            {"height": 1704, "type": "w", "width": 2560, "url": "..."}
          ],
          "text": "",
          "has_tags": false
        },
        "replies": {"count": 0, "new": 0},
        "is_one_time": false,
        "track_code": "story/3AAQAc4sVUAaAs4bMaesA84sVUAaBAAFoAagB6AIAA==",
        "type": "photo",
        "views": 0,
        "likes_count": 0,
        "reaction_set_id": "reactions",
        "is_restricted": true,
        "no_sound": false,
        "can_ask": 0,
        "can_ask_anonymous": 0,
        "narratives_count": 0,
        "can_use_in_narrative": false
      }
    ]
  }
}
```

### Коды ошибок

В ходе выполнения могут произойти общие ошибки.