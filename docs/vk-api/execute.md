# Execute API Method

Документация метода execute VK API.
Используется в: CreateVkRepostJob (пакетный репост в группы), ArchiveVkProductJob (пакетное удаление товаров).

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

VKScript — строго типизированное подмножество TypeScript. Поддерживает:
- базовые операции
- циклы
- условия
- работу с массивами
- вызовы API-методов через `API.<method>(<params>)`

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

### Ограничения

- **Rate limit VK API:** не больше 3 запросов в секунду.
- **execute:** один вызов execute считается за 1 запрос, но внутри можно
  выполнить до 25 вызовов API-методов.
- Запросы, которые можно сгруппировать для одного агента, обязательно
  объединять в один вызов execute с кодом на VKScript (согласно AGENTS.md).