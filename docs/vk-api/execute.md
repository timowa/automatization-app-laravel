# Execute API Method

Документация метода execute VK API.
Используется в: CreateVkRepostJob (пакетный репост в группы), ArchiveVkProductJob (пакетное удаление товаров), ProcessFriendRequestWelcomeJob (история переписки и приветствие одним скриптом).

Источник: https://dev.vk.com/ru/method/execute

---

## execute

Универсальный метод, который позволяет выполнять последовательность API-методов
на сервере VK с использованием языка VKScript.

### Ключи доступа

- ключ доступа пользователя

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `code` | string | Код алгоритма на языке VKScript. |

### Результат

Возвращает результат выполнения алгоритма. Тип данных зависит от того,
что возвращает код VKScript.

### Коды ошибок

В ходе выполнения могут произойти общие ошибки.

---

## VKScript

Единственное официальное описание языка — страница метода [execute](https://dev.vk.com/ru/method/execute) и её архив: [vk.com/dev/execute](https://web.archive.org/web/20150429005235/http://vk.com/dev/execute). Это не TypeScript и не JavaScript: объектов по ссылке нет, функций объявлять нельзя, `undefined` нет.

Поддерживается:

- арифметика, конкатенация строк через `+`, `parseInt` и `parseDouble`;
- массивы и объекты (`[1, 2]`, `{key: "value"}`), `.length`, `push`, `pop`, `shift`, `unshift`, `slice`, `splice`, `substr`, `split`, `indexOf`, `delete`;
- `while`, `if` / `else`;
- сравнения `<`, `>`, `<=`, `>=`, `==`, `!=` — только два числа, две строки или строка и число;
- логические `&&` и `||`, но оба операнда уже должны быть boolean;
- вызов `API.section.method({...})`, не больше 25 вызовов на один `execute`;
- оператор `@.` для поля массива объектов;
- код заканчивается `return`. Операторы разделяются `;`.

В условие `if` и `while` можно передать только boolean. Запись `if (history)` или `if (item.attachment)` даёт ошибку `Boolean value needed in a condition`: объект, число и строка сами по себе условием не являются. Проверка — только сравнение, например `if (items[j].out == 1)`. Сравнение с `null` или с другим типом даёт `Comparing values of different or unsupported types`. Оператор `in` не компилируется.

Ошибка отдельного API-вызова внутри `execute` не обязана обрывать весь код: результат такого вызова — `false`. Текст ошибки ВКонтакте кладёт в `execute_errors`, но `getRequest()->post()` возвращает только `response` и этот текст не отдаёт.

`false > 0` и любое сравнение `false` с числом обрывают весь скрипт (`Comparing values of different or unsupported types`). Проверенный обход: `var sentId = parseInt("" + sent); var sentOk = sentId > 0;`. Для `false` конкатенация даёт пустую строку, `parseInt` даёт `0`, `0 > 0` — boolean `false`. Для номера сообщения `parseInt` возвращает само число. `null` в литерале объекта допустим: `{"message_id": null, "error": null}`.

### Использование в проекте

#### CreateVkRepostJob — пакетный репост

В `VkApiService::createReposts()` execute используется для выполнения до 25 репостов
в один запрос. Полученные через `groups.get` (с фильтром moder) ID групп
объединяются с ID поста и отправляются одним вызовом execute.

```javascript
// Псевдокод VKScript из проекта
var groups = [group1, group2, ...]; // до 25 групп
var postId = "wall{owner_id}_{post_id}";
var result = [];
var i = 0;
while (i < groups.length && i < 25) {
    var groupId = groups[i];
    var response = API.wall.repost({
        "object": postId,
        "group_id": groupId
    });
    result.push({
        "group_id": groupId,
        "response": response
    });
    i = i + 1;
}
return result;
```

#### ArchiveVkProductJob — пакетное удаление товаров

В `ArchiveVkProductJob` execute используется для удаления нескольких товаров
в одной группе одним запросом (когда все товары в одной группе).

```javascript
// Псевдокод VKScript из проекта
var groupId = {group_id};
var productIds = [product1, product2, ...];
var result = [];
var i = 0;
while (i < productIds.length) {
    result.push(API.market.delete({
        "owner_id": -groupId,
        "item_id": productIds[i]
    }));
    i = i + 1;
}
return result;
```

#### ProcessFriendRequestWelcomeJob — пакет приветствий

`sendWelcomeBatch()`, `createReposts()`, пакеты товаров и список групп вызывают один и тот же `VkApiService::execute()`. Скрипт уходит в теле POST (`multipart/form-data`: `code`, `access_token`, `v`, `lang`). Адрес запроса — только `https://api.vk.com/method/execute`. Метод SDK `getRequest()->post()` кладёт параметры в строку адреса, и на длинном скрипте ВКонтакте отвечает `414 Request-URI Too Large`. Ответ разбирается так же, как у SDK: при коде не 200 — исключение клиента, при `error` — исключение API, наружу возвращается поле `response`.

В приветствие попадает не больше `VkApiService::WELCOME_BATCH_LIMIT` заявок (сейчас 3; технический потолок 12 = `floor(25 / 2)`): на заявку уходит `messages.getHistory` и, если `count` равен 0, ещё `messages.send`, а внутри `execute` не больше 25 вызовов. Один `ProcessFriendRequestWelcomeJob` передаёт заявки одного агента и делает один такой вызов.

```javascript
var items = [/* user_id, message, random_id, attachment */];
var res = [];
var i = 0;
while (i < items.length) {
    var item = items[i];
    var history = API.messages.getHistory({"user_id": item.user_id, "count": 1});
    var hasHistory = history.count > 0;
    if (hasHistory) {
        res.push({"user_id": item.user_id, "message_id": null, "error": "Агент уже написал пользователю"});
    } else {
        var hasAttachment = item.attachment.length > 0;
        var sent = null;
        if (hasAttachment) {
            sent = API.messages.send({"peer_id": item.user_id, "message": item.message, "random_id": item.random_id, "attachment": item.attachment, "dont_parse_links": 1});
        } else {
            sent = API.messages.send({"peer_id": item.user_id, "message": item.message, "random_id": item.random_id, "dont_parse_links": 1});
        }
        var sentId = parseInt("" + sent);
        var sentOk = sentId > 0;
        if (sentOk) {
            res.push({"user_id": item.user_id, "message_id": sentId, "error": null});
        } else {
            res.push({"user_id": item.user_id, "message_id": null, "error": sent});
        }
    }
    i = i + 1;
}
return res;
```

`hasHistory`, `hasAttachment` и `sentOk` — boolean, их можно передавать в `if`. Условие `if (history)` или `sent > 0` при ответе `false` скрипт не компилирует или обрывает.

### Ограничения

- **Rate limit VK API:** не больше 3 запросов в секунду.
- **execute:** один вызов execute считается за 1 запрос, но внутри можно
  выполнить до 25 вызовов API-методов.
- Запросы, которые можно сгруппировать для одного агента, обязательно
  объединять в один вызов execute с кодом на VKScript (согласно AGENTS.md).