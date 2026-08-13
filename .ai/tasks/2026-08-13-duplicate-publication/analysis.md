# Analysis — Дублирование Publication при обработке вебхука

## Task overview

При обработке вебхука создаётся 1 Offer, но 2 записи Publication. Причина — ProcessOfferListener регистрируется дважды для OfferCreatedEvent: один раз через явное объявление в `$listen` массиве EventServiceProvider, второй раз через автоматическое обнаружение (event discovery) в Laravel 13.

## Current architecture

### Цепочка обработки

```
POST /offer
  → OfferController::offer()
    → ReceiveOfferWebhookAction::execute()
      → Offer::create()                          — 1 запись
      → event(new OfferCreatedEvent())
        → [listener 1] ProcessOfferListener      — создаёт Publication #1
        → [listener 2] ProcessOfferListener@handle — создаёт Publication #2
```

`ProcessOfferListener implements ShouldQueue` — каждый слушатель ставит отдельную job в очередь. Queue worker обрабатывает обе job последовательно с интервалом ~5 секунд.

### Регистрация слушателей в Laravel 13

В Laravel 13 изменился механизм регистрации событий. `Application::configure()` вызывает `withEvents()` в `ApplicationBuilder`, который по умолчанию включает automatic event discovery:

```php
// Illuminate\Foundation\Configuration\ApplicationBuilder::withEvents()
public function withEvents(iterable|bool $discover = true)
{
    if ($discover === false) {
        AppEventServiceProvider::disableEventDiscovery();
    }
    if (! isset($this->pendingProviders[AppEventServiceProvider::class])) {
        $this->app->booting(function () {
            $this->app->register(AppEventServiceProvider::class);
        });
    }
    $this->pendingProviders[AppEventServiceProvider::class] = true;
    return $this;
}
```

`$discover = true` по умолчанию. Это означает, что `Illuminate\Foundation\Support\Providers\EventServiceProvider` включит automatic event discovery.

### Два EventServiceProvider'а загружены одновременно

В приложении загружены три провайдера:

```
Illuminate\Events\EventServiceProvider                    — базовый, singleton 'events'
App\Providers\EventServiceProvider                       — пользовательский, $listen массив
Illuminate\Foundation\Support\Providers\EventServiceProvider — базовый, event discovery
```

`App\Providers\EventServiceProvider` наследуется от `Illuminate\Foundation\Support\Providers\EventServiceProvider`.

### Как работает shouldDiscoverEvents

```php
// Illuminate\Foundation\Support\Providers\EventServiceProvider
public function shouldDiscoverEvents(): bool
{
    return get_class($this) === __CLASS__ && static::$shouldDiscoverEvents === true;
}
```

Метод проверяет: `get_class($this) === __CLASS__` — это означает, что discovery включается ТОЛЬКО если класс провайдера — именно `Illuminate\Foundation\Support\Providers\EventServiceProvider` (не наследник).

`App\Providers\EventServiceProvider` переопределяет `shouldDiscoverEvents()` и возвращает `false`:

```php
// App\Providers\EventServiceProvider
public function shouldDiscoverEvents(): bool
{
    return false;
}
```

### Откуда два слушателя

1. **App\Providers\EventServiceProvider** (из `bootstrap/providers.php`):
   - `$listen = [OfferCreatedEvent::class => [ProcessOfferListener::class]]`
   - `shouldDiscoverEvents() = false`
   - Регистрирует: `ProcessOfferListener` (без @handle)

2. **Illuminate\Foundation\Support\Providers\EventServiceProvider** (из `ApplicationBuilder::withEvents()`):
   - `shouldDiscoverEvents() = true` (get_class === __CLASS__ выполняется)
   - Сканирует `app/Listeners/` директорию
   - Находит `ProcessOfferListener`, видит метод `handle(OfferCreatedEvent $event)`
   - Регистрирует: `ProcessOfferListener@handle`

Результат: два слушателя для `OfferCreatedEvent`:
```
App\Listeners\ProcessOfferListener          — из $listen массива
App\Listeners\ProcessOfferListener@handle   — из event discovery
```

Оба вызываются при `event(new OfferCreatedEvent())`. Оба ставят job в очередь. Оба создают Publication.

### Доказательство

`event:list`:
```
App\Events\OfferCreatedEvent
  ⇂ App\Listeners\ProcessOfferListener (ShouldQueue)
  ⇂ App\Listeners\ProcessOfferListener@handle (ShouldQueue)
```

Сырой массив слушателей:
```php
['App\Listeners\ProcessOfferListener', 'App\Listeners\ProcessOfferListener@handle']
```

БД (10 publications на 5 offers — все парами с интервалом ~5 сек):
```
offer_id=3 → pub#1 (19:52:14), pub#2 (19:52:19)
offer_id=4 → pub#3 (19:58:22), pub#4 (19:58:27), pub#5 (20:00:18), pub#6 (20:00:23)
offer_id=5 → pub#9 (20:11:49), pub#10 (20:11:54)
```

## Affected areas

- `bootstrap/app.php` — конфигурация withEvents()
- `app/Providers/EventServiceProvider.php` — возможные изменения
- Код приложения (ReceiveOfferWebhookAction, ProcessOfferListener, CreatePublicationAction) — без изменений

## Existing implementation

### bootstrap/app.php

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('vk:publish-loop-stories')->dailyAt('10:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // ...
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ...
    })
    ->create();
```

`withEvents()` не вызывается явно — но `Application::configure()` вызывает его автоматически в ApplicationBuilder:

```php
return (new static::$applicationBuilder(new static($basePath)))
    ->withKernels()
    ->withEvents()    // ← вызывается автоматически, $discover = true
    ->withCommands()
    ->withProviders();
```

### app/Providers/EventServiceProvider.php

```php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OfferCreatedEvent::class => [
            ProcessOfferListener::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
```

### Illuminate\Foundation\Events\DiscoverEvents::getListenerEvents()

При сканировании `app/Listeners/` находит все public-методы, начинающиеся с `handle*` или `__invoke`, и определяет тип события по первому параметру:

```php
foreach ($listener->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ((! Str::is('handle*', $method->name) && ! Str::is('__invoke', $method->name)) ||
        ! isset($method->getParameters()[0])) {
        continue;
    }
    $listenerEvents[$listener->name.'@'.$method->name] =
        Reflector::getParameterClassNames($method->getParameters()[0]);
}
```

`ProcessOfferListener::handle(OfferCreatedEvent $event)` — попадает под критерии → регистрируется как `ProcessOfferListener@handle` для `OfferCreatedEvent`.

## Architecture decisions

### Решение: отключить event discovery в bootstrap/app.php

В `bootstrap/app.php` нужно явно вызвать `withEvents(false)`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withSchedule(...)
    ->withMiddleware(...)
    ->withExceptions(...)
    ->withEvents(false)   // ← отключить automatic event discovery
    ->create();
```

Это отключит event discovery в `Illuminate\Foundation\Support\Providers\EventServiceProvider`, и слушатели будут регистрироваться только через явный `$listen` массив в `App\Providers\EventServiceProvider`.

### Почему not shouldDiscoverEvents() = false

`App\Providers\EventServiceProvider::shouldDiscoverEvents()` уже возвращает `false`, но это не помогает, потому что:
- `Illuminate\Foundation\Support\Providers\EventServiceProvider` — отдельный провайдер, загружается ApplicationBuilder'ом
- Его `shouldDiscoverEvents()` возвращает `true` (get_class($this) === __CLASS__ выполняется)
- Он сканирует `app/Listeners/` независимо от App\Providers\EventServiceProvider

### Альтернативы (не рекомендуются)

1. **Удалить $listen массив и положиться только на discovery** — тогда нужно удалить `shouldDiscoverEvents() = false`, но это меняет устоявшийся паттерн проекта (явная регистрация).

2. **Удалить ProcessOfferListener из $listen и включить discovery** — то же самое, меняет паттерн.

3. **Добавить ProcessOfferListener implements ShouldBeDiscovered с shouldBeDiscovered() = false** — интерфейс существует, но это hack для одного listener'а, не решает проблему глобально.

### Финальное решение

`withEvents(false)` в `bootstrap/app.php` — однострочное изменение, отключает discovery глобально, оставляет явную регистрацию через `$listen`. Соответствует архитектуре проекта.

## Dependencies

Нет зависимостей от других задач. Однострочное изменение в `bootstrap/app.php`.

Execution order: единственная задача, не требует разделения на backend/frontend/review.

## Risks

1. **Другие события, рассчитывающие на discovery** — если в проекте есть listener'ы, не зарегистрированные в `$listen`, но рассчитывающие на automatic discovery, они перестанут работать. Проверка: в `app/Listeners/` только `ProcessOfferListener`, и он уже зарегистрирован в `$listen` — других listener'ов нет.

2. **Встроенные события Laravel** — Registered::class (email verification) обрабатывается через `configureEmailVerification()` в EventServiceProvider, не зависит от discovery. Не затрагивается.

3. **Очистка кэша** — после изменения `bootstrap/app.php` нужно перезапустить queue worker, чтобы он подхватил новую конфигурацию. PHP-FPM подхватит автоматически при следующем запросе.

4. **Существующие дубликаты в БД** — 10 publications на 5 offers уже созданы. Нужно ли их удалять? Это решает пользователь. Задача направлена на предотвращение новых дубликатов.

## Questions

Нет. Информации достаточно.

## Definition of Done

- [ ] В `bootstrap/app.php` добавлено `->withEvents(false)` перед `->create()`
- [ ] `event:list` показывает только одного слушателя для OfferCreatedEvent
- [ ] POST /offer с новым offer создаёт ровно 1 Publication
- [ ] POST /offer с новым offer создаёт ровно 1 набор PublicationTask (по сценарию)
- [ ] Queue worker перезапущен после изменения