# Backend: CreateVkProductJob — статус SUCCESS только при создании товаров

## Цель

CreateVkProductJob присваивает статус SUCCESS безусловно. Изменить: SUCCESS — только
если создан хотя бы один товар. FAILED — если ни один товар не создан.

## Контекст

CreateVkProductJob выполняет execute для создания товаров в нескольких группах.
Внутри цикла по чанкам есть try-catch, который "глотает" ошибки. После цикла
бездоговорочно ставится SUCCESS.

Публикация с 0 товаров вводит в заблуждение: зависимые задачи (edit/archive) могут
пытаться работать с несуществующими товарами.

## Существующая реализация

Файл: app/Jobs/CreateVkProductJob.php

Строки 90-143 (упрощённо):
```
foreach ($chunks as $index => $chunk) {
    try {
        $results = $vkApi->createProductsBatch(...);
        foreach ($results as $result) {
            // проверка productId
            // если валидный: VkProduct::create(...)
            // если невалидный: лог warning, continue
        }
    } catch (Throwable $th) {
        лог error  // ошибка "проглочена", цикл продолжается
    }
    usleep между чанками
}

$task->update(['status' => PublicationTaskStatus::SUCCESS]);  // ← БЕЗУСЛОВНО
Log::channel('job')->info('Товары по офферу созданы', ...);
$taskDependencyResolver->release($this->taskId);
$taskDispatcher->dispatch($task->publication_id);
```

## Необходимые изменения

### 1. Счётчик созданных товаров

Перед циклом (перед строкой 90):
```php
$createdCount = 0;
```

Внутри цикла, после VkProduct::create (после строки 128):
```php
$createdCount++;
```

### 2. Проверка после цикла

Заменить безусловный SUCCESS (строки 143-150) на:
```php
if ($createdCount === 0) {
    throw new \RuntimeException('Не создано ни одного товара');
}

$task->update(['status' => PublicationTaskStatus::SUCCESS]);
Log::channel('job')->info('Товары по офферу созданы', [
    'offer' => $offer->code,
    'count' => $createdCount,
]);
$taskDependencyResolver->release($this->taskId);
$taskDispatcher->dispatch($task->publication_id);
```

RuntimeException будет пойман существующим catch (\Throwable $e) на строке 164,
который уже корректно ставит FAILED и логирует ошибку.

### 3. Дополнительно: лог количества при частичном успехе

Если часть товаров не создалась (createdCount < общего количества групп),
добавить warning лог:
```php
if ($createdCount < $groups->count()) {
    Log::channel('job')->warning('Созданы не все товары', [
        'offer' => $offer->code,
        'created' => $createdCount,
        'total' => $groups->count(),
    ]);
}
```

## Затрагиваемые файлы

- `app/Jobs/CreateVkProductJob.php`

## Ограничения

- RuntimeException ловится в существующем catch (\Throwable $e) — НЕ нужно добавлять
  новый catch-блок
- release() и dispatch() вызываются ТОЛЬКО при SUCCESS — при FAILED они не вызываются
- Зависимые задачи остаются в WAITING при FAILED — это текущее поведение PublicationTaskDependencyResolver,
  не требует изменения в данной задаче

## Out of scope

- PublicationTaskDependencyResolver — не переводит WAITING → FAILED при failure родителя.
  Это отдельная доработка (если требуется).
- ArchiveVkProductJob — имеет аналогичную проблему (SUCCESS безусловно, строка 124),
  но в ArchiveVkProductJob нет внутреннего try-catch, поэтому исключение прерывает
  выполнение и попадает в catch. Если нужно — отдельная задача.

## Definition of Done

- [ ] Переменная $createdCount инкрементируется при каждом VkProduct::create
- [ ] Если $createdCount === 0 — throw RuntimeException (ловится существующим catch)
- [ ] SUCCESS ставится только если $createdCount > 0
- [ ] В логе job: info содержит count созданных товаров
- [ ] В логе job: warning если созданы не все товары
- [ ] При FAILED — зависимые задачи не выполняются (release не вызывается)
- [ ] Тест: 0 товаров → FAILED
- [ ] Тест: 1+ товаров → SUCCESS