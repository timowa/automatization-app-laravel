# Дублирование Publication при обработке вебхука

## Описание задачи

При обработке вебхука POST /offer создаётся 1 Offer, но 2 записи Publication (и, соответственно, 2 набора PublicationTask). Нужно устранить дублирование.

## Контекст

Процесс обработки вебхука:
```
POST /offer → ReceiveOfferWebhookAction::execute()
  → Offer::create()              (1 запись)
  → event(new OfferCreatedEvent())
    → ProcessOfferListener       (ShouldQueue)
      → CreatePublicationAction::execute()
        → Publication::create()  (должна быть 1, но создаётся 2)
```

В БД подтверждено:
```
offer_id=3 → 2 publications (19:52:14 и 19:52:19, ~5 секунд разницы)
offer_id=4 → 4 publications (пары по 5 секунд)
offer_id=5 → 2 publications (20:11:49 и 20:11:54, ~5 секунд разницы)
```

Каждая пара создана с интервалом ~5 секунд — это характерно для очереди с retry_after=90 и повторной обработкой.

При проверке `event:list` обнаружено:
```
App\Events\OfferCreatedEvent
  ⇂ App\Listeners\ProcessOfferListener (ShouldQueue)
  ⇂ App\Listeners\ProcessOfferListener@handle (ShouldQueue)
```

Два слушателя для одного события:
1. `App\Listeners\ProcessOfferListener` — из `$listen` массива в `App\Providers\EventServiceProvider`
2. `App\Listeners\ProcessOfferListener@handle` — из автоматического обнаружения (event discovery)

## Требования

1. OfferCreatedEvent должен обрабатываться ровно одним слушателем ProcessOfferListener.
2. Создаваться ровно 1 Publication на 1 Offer при одном сценарии.
3. Устранить дублирование без изменения бизнес-логики.