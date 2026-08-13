Task:
Дублирование Publication при обработке вебхука

Status:
PLANNED

Created:
2026-08-13

Current phase:
ANALYSIS

Backend:
READY

Frontend:
N/A

Review:
READY

Blockers:
Нет.

Notes:
Причина — двойная регистрация ProcessOfferListener для OfferCreatedEvent.
Источники регистрации:
1. $listen массив в App\Providers\EventServiceProvider → ProcessOfferListener
2. Automatic event discovery (withEvents(true) по умолчанию в Laravel 13) → ProcessOfferListener@handle
Решение: withEvents(false) в bootstrap/app.php — отключить discovery, оставить явную регистрацию.