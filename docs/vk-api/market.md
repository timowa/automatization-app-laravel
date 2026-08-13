# Market API Methods

Документация методов раздела Market VK API.
Используются в: CreateVkProductJob (market.add), EditVkProductJob (market.edit), ArchiveVkProductJob (market.delete).

Источник: https://dev.vk.com/ru/method/market

---

## market.add

Метод добавляет новый товар.

### Ключи доступа

- ключ доступа пользователя (требуется право доступа: market)

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `owner_id` | integer | Обязательный. Идентификатор владельца товара. Для сообщества — со знаком `-`. |
| `name` | string | Обязательный. Название товара. Макс. 100, мин. 4 символа (в cp1251). |
| `description` | text | Обязательный. Описание товара. |
| `category_id` | positive | Обязательный. Идентификатор категории товара. Если неизвестен — укажите 1. |
| `price` | string | Цена товара. |
| `old_price` | string | Старая цена товара. |
| `deleted` | checkbox | 1 — товар недоступен. 0 — товар доступен. |
| `main_photo_id` | positive | Идентификатор фотографии обложки товара. |
| `photo_ids` | integer | Идентификаторы дополнительных фотографий через запятую. Максимум 4. |
| `video_ids` | integer | Идентификаторы видео товара. |
| `url` | string | Ссылка на сайт товара. Макс. 320, мин. 0 символов. |
| `variant_ids` | integer | Список id вариантов свойств. Не более 2 значений. |
| `is_main_variant` | checkbox | Признак главного товара в группе. |
| `dimension_width` | positive | Ширина в миллиметрах. |
| `dimension_height` | positive | Высота в миллиметрах. |
| `dimension_length` | positive | Глубина в миллиметрах. |
| `weight` | positive | Вес в граммах. |
| `sku` | string | Артикул товара. Макс. 50 символов. |
| `stock_amount` | integer | Количество товара в наличии: -1 — неограничено, 0 — недоступен, >0 — количество. |

### Результат

Метод возвращает идентификатор добавленного товара (string).

### Пример ответа

```json
{
  "response": "1"
}
```

### Коды ошибок

| Код | Описание |
|-----|----------|
| 205 | Access denied |
| 1405 | Too many items |
| 1406 | Too many items in album |
| 1408 | Item has bad links in description |
| 1416 | Variant not found |
| 1417 | Property not found |
| 1425 | Grouping must have two or more items |
| 1426 | Item must have distinct properties |
| 1433 | Invalid image crop format |
| 1434 | Crop bottom right corner is outside of the image |
| 1435 | Crop size is less than the minimum |
| 1438 | Market not enabled |

---

## market.edit

Метод редактирует информацию о товаре.

### Ключи доступа

- ключ доступа пользователя (требуется право доступа: market)

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `owner_id` | integer | Обязательный. Идентификатор владельца. Для сообщества — со знаком `-`. |
| `item_id` | positive | Обязательный. Идентификатор товара. |
| `name` | string | Новое название. Макс. 100, мин. 4 символа. |
| `description` | text | Новое описание. |
| `category_id` | positive | Идентификатор категории. |
| `price` | string | Цена. |
| `old_price` | string | Старая цена. |
| `deleted` | checkbox | 1 — недоступен. 0 — доступен. |
| `main_photo_id` | positive | Идентификатор фотографии обложки. |
| `photo_ids` | integer | Идентификаторы дополнительных фотографий. |
| `video_ids` | integer | Идентификаторы видео. |
| `url` | string | Ссылка на сайт. Игнорируется в расширенном магазине. Макс. 320, мин. 0. |
| `variant_ids` | integer | Список id вариантов свойств. Не более 2. |
| `is_main_variant` | checkbox | Признак главного товара в группе. |
| `dimension_width` | positive | Ширина в мм. |
| `dimension_height` | positive | Высота в мм. |
| `dimension_length` | positive | Глубина в мм. |
| `weight` | positive | Вес в граммах. |
| `sku` | string | Артикул. Макс. 50. |
| `stock_amount` | integer | Количество: -1 — неограничено, 0 — недоступен, >0 — количество. |

### Результат

Возвращает 1, если информация изменена, и 0 в случае ошибки.

### Пример ответа

```json
{
  "response": 1
}
```

### Коды ошибок

| Код | Описание |
|-----|----------|
| 205 | Access denied |
| 1403 | Item not found |
| 1408 | Item has bad links in description |
| 1412 | Grouping items with different properties |
| 1413 | Grouping already has such variant |
| 1416 | Variant not found |
| 1417 | Property not found |
| 1425 | Grouping must have two or more items |
| 1426 | Item must have distinct properties |
| 1433 | Invalid image crop format |
| 1434 | Crop bottom right corner is outside of the image |
| 1435 | Crop size is less than the minimum |
| 1438 | Market not enabled |

---

## market.delete

Удаляет товар.

> **Примечание:** В проекте vk19-app метод `market.delete` используется в
> `ArchiveVkProductJob` для архивации товаров. Название job подразумевает
> архивацию, но фактически вызывается удаление. Это может быть намереренным
> решением или стоит рассмотреть использование `market.delete` с параметром
> `deleted=1` через `market.edit` для архивации без полного удаления.

### Ключи доступа

- ключ доступа пользователя (требуется право доступа: market)

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `owner_id` | integer | Обязательный. Идентификатор владельца. Для сообщества — со знаком `-`. |
| `item_id` | positive | Обязательный. Идентификатор товара. |

### Результат

После успешного выполнения возвращает 1.

### Коды ошибок

| Код | Описание |
|-----|----------|
| 205 | Access denied |
| 1403 | Item not found |
| 1438 | Market not enabled |