# Analysis

## Task overview

CreateVkProductJob присваивает PublicationTask статус SUCCESS безусловно — даже если
ни один товар не был создан. Нужно сделать SUCCESS условным: только если хотя бы один
товар создан. Если все товары не созданы — FAILED.

## Current architecture

### CreateVkProductJob (app/Jobs/CreateVkProductJob.php)

Поток выполнения:
1. PROCESSING
2. Загрузка фото через uploadMarketPhoto (может вернуть пустой массив)
3. Цикл по чанкам групп (до 25 в чанке):
   - createProductsBatch (execute) — может выбросить исключение (catch на строке 130)
   - Цикл по результатам:
     - Если response null или productId null/0 → лог warning, continue (не создаёт VkProduct)
     - Если productId валидный → VkProduct::create
4. БЕЗУСЛОВНО: SUCCESS (строка 143) ← ПРОБЛЕМА
5. release + dispatch

Проблема: на строке 143 `$task->update(['status' => PublicationTaskStatus::SUCCESS])`
выполняется всегда, независимо от того, были ли созданы товары.

### Сценарии failures

1. **execute полностью упал** — catch на строке 130 логирует ошибку, но цикл продолжается.
   Если все чанки упали — 0 товаров. Статус: SUCCESS (неправильно).

2. **execute вернул результаты, но все с ошибками** — productId null/0 для всех групп.
   0 товаров. Статус: SUCCESS (неправильно).

3. **Фото не загрузилось** — uploadMarketPhoto вернул пустой массив. execute может
   отработать (market.add с main_photo_id=null), но может вернуть ошибки. Статус: SUCCESS.

4. **Часть чанков упала, часть успешна** — N товаров создано. Статус: SUCCESS (правильно,
   если N > 0).

## Affected areas

- `app/Jobs/CreateVkProductJob.php` — основное изменение

## Existing implementation

Другие jobs для сравнения:
- CreateVkPostJob — SUCCESS после создания поста (строка 89), FAILED при исключении
- CreateVkRepostJob — SUCCESS после репостов (строка 92), FAILED при исключении
- CreateVkCommentJob — SUCCESS после создания комментария (строка 75)
- ArchiveVkProductJob — SUCCESS безусловно (строка 124), тоже может иметь ту же проблему

Паттерн: все jobs ставят SUCCESS в конце try-блока, FAILED в catch. Но CreateVkProductJob
имеет внутренний try-catch внутри цикла, который "глотает" ошибки отдельных чанков.

## Architecture decisions

### Решение: счётчик созданных товаров

Завести переменную `$createdCount = 0` перед циклом. Увеличивать при каждом успешном
VkProduct::create. После цикла:
- Если `$createdCount > 0` → SUCCESS
- Если `$createdCount === 0` → FAILED с ошибкой "Не создано ни одного товара"

Псевдокод:
```
createdCount = 0

foreach (chunks as chunk):
    try:
        results = createProductsBatch(...)
        foreach results:
            if productId валидный:
                VkProduct::create(...)
                createdCount++
            else:
                лог warning
    catch:
        лог error

if createdCount === 0:
    throw new RuntimeException('Не создано ни одного товара')

// SUCCESS
```

Использовать throw для единообразия с остальными catch-блоками (NotFoundException, VkApiException).

### Альтернатива: без throw

```
if createdCount > 0:
    task->update(SUCCESS)
    release + dispatch
else:
    task->update(FAILED, 'Не создано ни одного товара')
    лог warning
```

Throw предпочтительнее — меньше дублирования, catch-блок уже есть и корректно ставит FAILED.

## Dependencies

Только backend. Не зависит от других задач.

## Risks

1. **Зависимые задачи** — если CreateVkProductJob переходит в FAILED, зависимые задачи
   (VK_EDIT_PRODUCT, VK_ARCHIVE_PRODUCT) не должны выполниться. PublicationTaskDependencyResolver
   должен корректно обрабатывать FAILED статус (не переводить зависимые в pending).
   Нужно проверить PublicationTaskDependencyResolver.

2. **Частичный успех** — если 5 из 11 товаров созданы, статус SUCCESS. Это правильно:
   товар создан, зависимые задачи (edit/archive) могут работать с созданными товарами.

## Definition of Done

- [ ] SUCCESS только если создан хотя бы один товар
- [ ] FAILED если ни один товар не создан
- [ ] Лог в канале job: warning при FAILED с указанием количества созданных товаров
- [ ] Зависимые задачи не выполняются при FAILED (проверить DependencyResolver)