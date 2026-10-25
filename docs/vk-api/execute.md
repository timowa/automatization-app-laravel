# Execute API Method

Документация метода execute VK API.
Используется в: CreateVkRepostJob (пакетный репост в группы), ArchiveVkProductJob (пакетное удаление товаров), ProcessFriendRequestWelcomeJob (до 25 проверок истории и до 25 личных сообщений одного агента).

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

Ошибка отдельного API-вызова внутри `execute` не обязана обрывать весь код: на его месте в массиве ответа оказывается `false`. Текст такой ошибки ВКонтакте кладёт в `execute_errors`, но `getRequest()->post()` возвращает только `response` и этот текст не отдаёт. Поэтому пачку вызовов возвращаем списком `return [API....(), API....()];` и разбираем её в PHP, без ветвления по результату внутри VKScript.

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

`findUsersWithOutgoingMessages()` и `sendDirectMessages()` вызывают `execute` так же, как `createReposts`: `$this->client->getRequest()->post('execute', $this->token, ['code' => $code])`. Код — список до 25 вызовов без `if`: `return [API.messages.getHistory(...), ...]` и `return [API.messages.send(...), ...]`. Вложение письма подставляется в параметры в PHP. Клиент SDK возвращает только поле `response`; неуспешный вызов внутри списка приходит как `false`.

### Ограничения

- **Rate limit VK API:** не больше 3 запросов в секунду.
- **execute:** один вызов execute считается за 1 запрос, но внутри можно
  выполнить до 25 вызовов API-методов.
- Запросы, которые можно сгруппировать для одного агента, обязательно
  объединять в один вызов execute с кодом на VKScript (согласно AGENTS.md).