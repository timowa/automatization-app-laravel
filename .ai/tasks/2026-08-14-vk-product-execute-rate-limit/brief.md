# Задача: Рефакторинг CreateVkProductJob — execute и rate limit

## Описание

CreateVkProductJob создаёт товары в нескольких группах VK. Текущая реализация перебирает
группы в цикле и для каждой вызывает отдельные API-методы (photos.getMarketUploadServer,
upload, photos.saveMarketPhoto, market.add). Это приводит к превышению лимита VK API
(не более 3 запросов в секунду) — в логах зафиксированы ошибки "Too many requests per second".

Дополнительно: методы photos.getMarketUploadServer и photos.saveMarketPhoto удалены из
актуальной VK API документации (возвращают ошибку "Unknown method passed").

Требуется:
1. Использовать execute (VKScript) для группировки запросов к market.add
2. Решить проблему с устаревшими методами загрузки фото товара
3. Обеспечить соблюдение лимита — не более 3 запросов в секунду

## Контекст из логов

Логи: storage/logs/vk/posts/error-2026-08-14.log

Зафиксированные ошибки:
- "Too many requests per second" — при создании товаров в 11 группах
- "Unknown method passed" — photos.getMarketUploadServer / photos.saveMarketPhoto

Текущий цикл: 11 групп × ~4 API-запроса на группу = ~44 запроса за ~4 сек = ~11 запросов/сек
usleep(350_000) между группами недостаточен, т.к. каждый createProduct делает несколько запросов.