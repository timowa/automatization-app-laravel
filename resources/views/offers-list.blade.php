@extends('layout')

@section('title', 'Офферы')

@push('styles')
<style>
    .offer-status-icon,
    .offer-status-loader,
    .task-status-icon {
        cursor: pointer;
        position: relative;
        display: inline-flex;
    }
    .offer-instant-tooltip {
        position: absolute;
        z-index: 50;
        left: 0;
        top: calc(100% + 6px);
        max-width: 22rem;
        padding: 0.4rem 0.55rem;
        border-radius: 0.375rem;
        background: #111827;
        color: #fff;
        font-size: 0.75rem;
        line-height: 1.25rem;
        white-space: normal;
        box-shadow: 0 4px 12px rgb(0 0 0 / 0.18);
        pointer-events: none;
    }
</style>
@endpush

@section('content')
    <h2 class="text-xl font-bold mb-4">Офферы</h2>

    <form method="GET" action="/offers" class="bg-white rounded shadow p-4 mb-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $dir }}">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Агент</label>
            <select name="agent_id" class="w-full border rounded px-2 py-1.5 text-sm">
                <option value="">Все</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((string) $filters['agent_id'] === (string) $agent->id)>
                        {{ $agent->name }} ({{ $agent->phone }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Город</label>
            <select name="city" class="w-full border rounded px-2 py-1.5 text-sm">
                <option value="">Все</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->value }}" @selected((string) $filters['city'] === (string) $city->value)>
                        {{ $city->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Тип сделки</label>
            <select name="deal" class="w-full border rounded px-2 py-1.5 text-sm">
                <option value="">Все</option>
                @foreach ($deals as $deal)
                    <option value="{{ $deal->value }}" @selected((string) $filters['deal'] === (string) $deal->value)>
                        {{ $deal->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Статус</label>
            <select name="status" class="w-full border rounded px-2 py-1.5 text-sm">
                <option value="">Все</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected((string) $filters['status'] === (string) $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Код</label>
            <input type="text" name="code" value="{{ $filters['code'] }}" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="217-100">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm font-semibold">Найти</button>
            <a href="/offers" class="px-3 py-1.5 border rounded text-sm text-gray-600">Сбросить</a>
        </div>
    </form>

    @php
        $sortLink = function (string $column, string $label) use ($sort, $dir, $sortUrl): string {
            $arrow = '';
            if ($sort === $column) {
                $arrow = $dir === 'asc' ? ' ↑' : ' ↓';
            }

            return '<a href="'.e($sortUrl($column)).'" class="hover:text-blue-700">'.e($label).$arrow.'</a>';
        };
    @endphp

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm" id="offers-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('health', 'Статус') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('code', 'Код') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('agent', 'Агент') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('scenario', 'Сценарий') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('id', 'ID') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('offer_id', 'Внешний ID') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('stage', 'Этап') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('status', 'Статус объекта') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('price', 'Цена') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('area', 'Площадь') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('kitchen_area', 'Кухня') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('living_area', 'Жилая') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('city', 'Город') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('address', 'Адрес') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('deal', 'Сделка') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('category', 'Категория') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('rooms', 'Комнаты') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('rooms_offered', 'Комнат сдаётся') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('floor', 'Этаж') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('floors_total', 'Этажей') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('commission', 'Комиссия') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('deposit', 'Залог') !!}</th>
                    <th class="px-3 py-2 text-left whitespace-nowrap">{!! $sortLink('created_at', 'Создан') !!}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($offers as $row)
                    @php
                        /** @var \App\Models\Offer $offer */
                        $offer = $row['offer'];
                        $health = $row['health'];
                        $scenario = $row['scenario'];
                    @endphp
                    <tr class="offer-row align-top" data-offer-id="{{ $offer->id }}">
                        <td class="px-3 py-2">
                            <div class="offer-status flex flex-col items-start gap-1" data-status="{{ $health['status'] }}" data-offer-id="{{ $offer->id }}">
                                <span
                                    class="offer-status-icon {{ $health['status'] === 'processing' ? 'hidden' : '' }}"
                                    data-tooltip="{{ $health['tooltip'] }}"
                                >
                                    @if ($health['status'] === 'ok')
                                        <svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M12 9v4"/>
                                            <path d="M12 17h.01"/>
                                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        </svg>
                                    @endif
                                </span>
                                <span class="offer-status-loader {{ $health['status'] === 'processing' ? '' : 'hidden' }}" data-tooltip="Задачи ещё выполняются">
                                    <svg class="w-5 h-5 text-gray-500 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </span>
                                <span class="offer-retry-slot">
                                    @if ($health['can_retry'])
                                        <button type="button" class="offer-retry text-xs text-gray-500 hover:text-gray-800 underline" data-offer-id="{{ $offer->id }}">повторить</button>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <a href="/offers?code={{ urlencode($offer->code) }}" class="text-blue-600 hover:underline font-medium">{{ $offer->code }}</a>
                            <div>
                                <button type="button" class="offer-toggle-tasks mt-1 text-xs text-gray-500 hover:text-gray-800 underline" data-offer-id="{{ $offer->id }}">
                                    Посмотреть задачи
                                </button>
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ $offer->agent?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $scenario !== '' ? $scenario : '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->id }}</td>
                        <td class="px-3 py-2">{{ $offer->offer_id ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->stage ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->status()?->label() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->price !== null ? number_format((int) $offer->price, 0, '.', ' ') : '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->area ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->kitchen_area ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->living_area ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->city()?->label() ?? '—' }}</td>
                        <td class="px-3 py-2 max-w-xs truncate" title="{{ $offer->getAddressFromLocation() }}">{{ $offer->location['address'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->deal()?->label() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->category()?->label() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->rooms ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->rooms_offered ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->floor ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->floors_total ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->commission ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $offer->deposit ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ formatUiDate($offer->created_at) }}</td>
                    </tr>
                    <tr class="offer-tasks-row hidden bg-gray-50" data-offer-id="{{ $offer->id }}">
                        <td colspan="23" class="px-4 py-3">
                            <div class="offer-tasks-content text-sm text-gray-500">Загрузка…</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $offers->links() }}
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}';

    const statusIcons = {
        ok: `<svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>`,
        warning: `<svg class="w-5 h-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>`,
    };

    const pollTimers = {};

    const hideInstantTooltip = (icon) => {
        icon.querySelector('.offer-instant-tooltip')?.remove();
    };

    const showInstantTooltip = (icon) => {
        const text = icon.getAttribute('data-tooltip');
        if (!text) return;
        hideInstantTooltip(icon);
        const tip = document.createElement('div');
        tip.className = 'offer-instant-tooltip';
        tip.textContent = text;
        icon.appendChild(tip);
    };

    const bindInstantTooltip = (icon) => {
        if (!icon || icon.dataset.tooltipBound === '1') return;
        icon.dataset.tooltipBound = '1';
        icon.addEventListener('mouseenter', () => showInstantTooltip(icon));
        icon.addEventListener('mouseleave', () => hideInstantTooltip(icon));
    };

    document.querySelectorAll('.offer-status-icon, .offer-status-loader').forEach(bindInstantTooltip);

    const escapeAttr = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const taskStatusIcon = (task) => {
        let svg = `<svg class="w-4 h-4 text-gray-400 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>`;
        if (task.status === 'success') {
            svg = `<svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>`;
        } else if (task.status === 'failed') {
            svg = `<svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>`;
        }

        return `<span class="task-status-icon" data-tooltip="${escapeAttr(task.tooltip || task.status_label)}">${svg}</span>`;
    };

    const renderTasksHtml = (tasks) => {
        if (!tasks || tasks.length === 0) {
            return '<span class="text-gray-500">Задач нет</span>';
        }

        const rows = tasks.map((task) => `
            <tr>
                <td class="px-2 py-1">${taskStatusIcon(task)}</td>
                <td class="px-2 py-1">${task.title}</td>
                <td class="px-2 py-1 text-gray-600">${task.status_label}</td>
                <td class="px-2 py-1 whitespace-nowrap">${task.finished_at}</td>
            </tr>
        `).join('');

        return `
            <table class="min-w-[32rem] text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="px-2 py-1">Статус</th>
                        <th class="px-2 py-1">Задача</th>
                        <th class="px-2 py-1">Состояние</th>
                        <th class="px-2 py-1">Дата</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    };

    const applyTasks = (offerId, tasks) => {
        const tasksRow = document.querySelector(`.offer-tasks-row[data-offer-id="${offerId}"]`);
        if (!tasksRow || tasksRow.classList.contains('hidden')) return;
        const content = tasksRow.querySelector('.offer-tasks-content');
        content.innerHTML = renderTasksHtml(tasks);
        content.querySelectorAll('.task-status-icon').forEach(bindInstantTooltip);
    };

    const ensureRetryButton = (statusBox, offerId, canRetry) => {
        const slot = statusBox.querySelector('.offer-retry-slot');
        if (!slot) return;
        let btn = slot.querySelector('.offer-retry');
        if (canRetry) {
            if (!btn) {
                btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'offer-retry text-xs text-gray-500 hover:text-gray-800 underline';
                btn.dataset.offerId = offerId;
                btn.textContent = 'повторить';
                slot.appendChild(btn);
                bindRetryButton(btn);
            }
            btn.classList.remove('hidden');
        } else if (btn) {
            btn.classList.add('hidden');
        }
    };

    const applyOfferHealth = (statusBox, data) => {
        const offerId = statusBox.dataset.offerId;
        const icon = statusBox.querySelector('.offer-status-icon');
        const loader = statusBox.querySelector('.offer-status-loader');
        const status = data.status || 'warning';
        const tooltip = data.tooltip || '';

        statusBox.dataset.status = status;

        if (status === 'processing') {
            icon.classList.add('hidden');
            loader.classList.remove('hidden');
            loader.setAttribute('data-tooltip', tooltip || 'Задачи ещё выполняются');
            bindInstantTooltip(loader);
        } else {
            loader.classList.add('hidden');
            icon.classList.remove('hidden');
            icon.innerHTML = statusIcons[status] || statusIcons.warning;
            icon.setAttribute('data-tooltip', tooltip);
            hideInstantTooltip(icon);
            bindInstantTooltip(icon);
        }

        ensureRetryButton(statusBox, offerId, !!data.can_retry);

        if (Array.isArray(data.tasks)) {
            applyTasks(offerId, data.tasks);
        }
    };

    const stopPolling = (offerId) => {
        if (pollTimers[offerId]) {
            clearTimeout(pollTimers[offerId]);
            delete pollTimers[offerId];
        }
    };

    const pollOfferStatus = (offerId, attempt = 0) => {
        stopPolling(offerId);
        const statusBox = document.querySelector(`.offer-status[data-offer-id="${offerId}"]`);
        if (!statusBox) return;

        pollTimers[offerId] = setTimeout(async () => {
            try {
                const response = await fetch(`/offers/${offerId}/status`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                applyOfferHealth(statusBox, data);
                if (data.status === 'processing' && attempt < 60) {
                    pollOfferStatus(offerId, attempt + 1);
                }
            } catch (e) {
                if (attempt < 60) {
                    pollOfferStatus(offerId, attempt + 1);
                }
            }
        }, attempt === 0 ? 800 : 2000);
    };

    const loadTasks = async (offerId) => {
        const tasksRow = document.querySelector(`.offer-tasks-row[data-offer-id="${offerId}"]`);
        if (!tasksRow) return;
        const content = tasksRow.querySelector('.offer-tasks-content');
        content.innerHTML = 'Загрузка…';
        try {
            const response = await fetch(`/offers/${offerId}/tasks`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            content.innerHTML = renderTasksHtml(data.tasks || []);
            content.querySelectorAll('.task-status-icon').forEach(bindInstantTooltip);
        } catch (e) {
            content.innerHTML = '<span class="text-red-600">Не удалось загрузить задачи</span>';
        }
    };

    document.querySelectorAll('.offer-toggle-tasks').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const offerId = btn.dataset.offerId;
            const tasksRow = document.querySelector(`.offer-tasks-row[data-offer-id="${offerId}"]`);
            if (!tasksRow) return;

            const isHidden = tasksRow.classList.contains('hidden');
            document.querySelectorAll('.offer-tasks-row').forEach((row) => row.classList.add('hidden'));
            if (!isHidden) return;

            tasksRow.classList.remove('hidden');
            await loadTasks(offerId);
        });
    });

    const bindRetryButton = (btn) => {
        if (btn.dataset.retryBound === '1') return;
        btn.dataset.retryBound = '1';
        btn.addEventListener('click', async () => {
            const offerId = btn.dataset.offerId;
            const statusBox = document.querySelector(`.offer-status[data-offer-id="${offerId}"]`);
            if (!statusBox) return;

            const icon = statusBox.querySelector('.offer-status-icon');
            const loader = statusBox.querySelector('.offer-status-loader');
            icon.classList.add('hidden');
            loader.classList.remove('hidden');
            btn.classList.add('hidden');

            try {
                const response = await fetch(`/offers/${offerId}/retry`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Ошибка повтора');
                }

                applyOfferHealth(statusBox, data);
                if (data.status === 'processing') {
                    pollOfferStatus(offerId);
                }
            } catch (e) {
                applyOfferHealth(statusBox, {
                    status: 'warning',
                    tooltip: e.message || 'Не удалось повторить',
                    can_retry: true,
                });
            }
        });
    };

    document.querySelectorAll('.offer-retry').forEach(bindRetryButton);

    document.querySelectorAll('.offer-status[data-status="processing"]').forEach((statusBox) => {
        pollOfferStatus(statusBox.dataset.offerId);
    });
});
</script>
@endpush
