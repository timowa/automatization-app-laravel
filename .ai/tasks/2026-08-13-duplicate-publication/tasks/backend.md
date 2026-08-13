# Backend Task — Дублирование Publication при обработке вебхука

## Цель

Устранить дублирование Publication при обработке вебхука. Сейчас создаётся 2 записи Publication на 1 Offer, потому что ProcessOfferListener регистрируется дважды для OfferCreatedEvent.

## Контекст

Laravel 13.24. `Application::configure()` в `bootstrap/app.php` автоматически вызывает `withEvents()` с параметром `$discover = true`, что включает automatic event discovery. Это означает, что `Illuminate\Foundation\Support\Providers\EventServiceProvider` сканирует директорию `app/Listeners/` и автоматически регистрирует все listener'ы, находя их методы `handle*` и `__invoke`.

Параллельно `App\Providers\EventServiceProvider` (из `bootstrap/providers.php`) регистрирует тот же `ProcessOfferListener` через явный `$listen` массив.

Результат: два слушателя для OfferCreatedEvent:
```
App\Listeners\ProcessOfferListener          — из $listen массива
App\Listeners\ProcessOfferListener@handle   — из event discovery
```

Оба реализуют ShouldQueue → оба ставят job в очередь → оба создают Publication.

Доказательство: `php artisan event:list` показывает двух слушателей. В БД 10 publications на 5 offers, все парами с интервалом ~5 секунд.

## Существующая реализация

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
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminAuthMiddleware::class,
            'api_key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        ]);
        $middleware->preventRequestForgery(except: [
            'offer',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();
```

`withEvents()` не вызывается явно, но `ApplicationBuilder` вызывает его автоматически:

```php
// Illuminate\Foundation\Configuration\ApplicationBuilder
return (new static::$applicationBuilder(new static($basePath)))
    ->withKernels()
    ->withEvents()    // ← $discover = true (default)
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

`shouldDiscoverEvents() = false` не помогает, потому что отдельный базовый `Illuminate\Foundation\Support\Providers\EventServiceProvider` (загружаемый ApplicationBuilder'ом) имеет `shouldDiscoverEvents() = true` и сканирует listeners независимо.

### app/Listeners/ProcessOfferListener.php

```php
class ProcessOfferListener implements ShouldQueue
{
    public function handle(OfferCreatedEvent $event): void
    {
        // ... создаёт Publication через CreatePublicationAction
    }
}
```

### app/Listeners/ — содержимое

В директории `app/Listeners/` находится только один файл: `ProcessOfferListener.php`. Других listener'ов нет. Все события регистрируются явно через `$listen` массив.

## Необходимые изменения

### 1. bootstrap/app.php — отключить event discovery

Добавить `->withEvents(false)` в цепочку вызовов перед `->create()`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withSchedule(...)
    ->withMiddleware(...)
    ->withExceptions(...)
    ->withEvents(false)
    ->create();
```

Это передаёт `$discover = false` в `ApplicationBuilder::withEvents()`, который вызывает `AppEventServiceProvider::disableEventDiscovery()`. После этого `Illuminate\Foundation\Support\Providers\EventServiceProvider` перестанет сканировать `app/Listeners/`.

### 2. Перезапуск queue worker

После изменения `bootstrap/app.php` нужно перезапустить queue worker, чтобы он подхватил новую конфигурацию:

```bash
# Если queue:work запущен через docker exec:
docker exec vk19-app php artisan queue:restart
# Worker перезапустится автоматически (если запущен с --daemon)
# Или вручную перезапустить процесс
```

PHP-FPM подхватит изменение автоматически при следующем запросе — перезапуск не требуется.

## Затрагиваемые файлы

- `bootstrap/app.php` — добавление `->withEvents(false)`

## API изменения

Нет.

## Модели / Миграции

Нет.

## Тесты

### Проверка регистрации слушателей

```bash
docker exec vk19-app php artisan event:list
```

Ожидаемый результат:
```
App\Events\OfferCreatedEvent
  ⇂ App\Listeners\ProcessOfferListener (ShouldQueue)
```

Только один слушатель (без `@handle` дубликата).

### Проверка создания Publication

```bash
# Отправить вебхук с новым offer
curl --noproxy '*' -X POST http://localhost:8080/offer \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <API_KEY>" \
  -d '{"offers":[...]}'

# Проверить количество publications
docker exec vk19-app php artisan tinker --execute="echo \App\Models\Publication::where('offer_id', <OFFER_ID>)->count();"
```

Ожидание: 1 publication для нового offer.

### Проверка существующими тестами

```bash
docker exec vk19-app php artisan test --filter=BackendImplementationTest
```

## Ограничения

- НЕ менять `app/Providers/EventServiceProvider.php` — `$listen` массив и `shouldDiscoverEvents()` остаются как есть
- НЕ менять `app/Listeners/ProcessOfferListener.php` — логика обработки без изменений
- НЕ менять `app/Actions/CreatePublicationAction.php` — создание публикаций без изменений
- НЕ удалять существующие дубликаты Publication из БД — это решает пользователь

## Out of scope

- Очистка существующих дубликатов Publication из БД
- Изменение логики обработки событий
- Изменение `app/Providers/EventServiceProvider.php`

## Definition of Done

- [ ] В `bootstrap/app.php` добавлен вызов `->withEvents(false)` перед `->create()`
- [ ] `php artisan event:list` показывает одного слушателя для OfferCreatedEvent
- [ ] POST /offer с новым offer создаёт ровно 1 Publication
- [ ] POST /offer с новым offer создаёт ровно 1 набор PublicationTask
- [ ] Queue worker перезапущен
- [ ] Тест `BackendImplementationTest` проходит