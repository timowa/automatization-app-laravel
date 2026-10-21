@extends('layout')

@section('title', 'Офферы')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.2/css/buttons.dataTables.min.css">
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

        .offers-table-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .offers-table-toolbar .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            float: none;
        }

        .offers-table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            scrollbar-width: thin;
            scrollbar-color: #6b7280 #f3f4f6;
        }

        .offers-table-scroll::-webkit-scrollbar {
            height: 10px;
        }

        .offers-table-scroll::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 999px;
        }

        #offers-table {
            width: 100% !important;
            border-collapse: collapse;
        }

        #offers-table thead th {
            background-color: #f9fafb;
            white-space: nowrap;
            user-select: none;
        }

        #offers-table.display tbody tr:hover {
            background-color: #f9fafb;
        }

        #offers-table th.col-price,
        #offers-table td.col-price {
            min-width: 7.5rem;
            white-space: nowrap;
        }

        .offer-status-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.25rem;
            color: #fff;
            white-space: nowrap;
        }

        div.dt-container {
            font-size: 0.875rem;
        }

        .offers-table-toolbar .dt-button {
            margin: 0 !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: 0.375rem !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            line-height: 1.25rem !important;
            box-shadow: none !important;
            cursor: pointer;
        }

        .offers-table-toolbar .dt-button.offers-btn-columns {
            background: #2563eb !important;
            border: 1px solid #1d4ed8 !important;
            color: #fff !important;
        }

        .offers-table-toolbar .dt-button.offers-btn-columns:hover,
        .offers-table-toolbar .dt-button.offers-btn-columns:focus {
            background: #1d4ed8 !important;
            border-color: #1e40af !important;
            color: #fff !important;
        }

        .offers-table-toolbar .dt-button.offers-btn-columns.active {
            background: #1e40af !important;
        }

        .offers-table-toolbar .dt-button.offers-btn-reset-columns {
            background: #f8fafc !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
        }

        .offers-table-toolbar .dt-button.offers-btn-reset-columns:hover,
        .offers-table-toolbar .dt-button.offers-btn-reset-columns:focus {
            background: #e2e8f0 !important;
            border-color: #64748b !important;
            color: #0f172a !important;
        }

        div.dt-button-collection {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            padding: 0.5rem;
            max-height: 70vh;
            overflow-y: auto;
            min-width: 12rem;
        }

        div.dt-button-collection .dt-button {
            display: block;
            width: 100%;
            text-align: left;
            margin: 0 0 0.15rem !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0.375rem !important;
            padding: 0.45rem 0.6rem !important;
            color: #1f2937 !important;
            font-weight: 500 !important;
        }

        div.dt-button-collection .dt-button:hover {
            background: #eff6ff !important;
            color: #1d4ed8 !important;
        }

        div.dt-button-collection .dt-button.dt-button-active {
            background: #dbeafe !important;
            color: #1e40af !important;
        }

        table.dataTable tbody tr.offer-tasks-child > td {
            background: #f9fafb;
            padding: 0.75rem 1rem;
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

    <div class="offers-table-toolbar" id="offers-table-toolbar"></div>

    <div class="bg-white rounded shadow offers-table-scroll" id="offers-table-scroll">
        <table class="min-w-full text-sm display nowrap" id="offers-table">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left no-colvis" data-col="health">{!! $sortLink('health', 'Статус') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="code">{!! $sortLink('code', 'Код') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="agent">{!! $sortLink('agent', 'Агент') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="scenario">{!! $sortLink('scenario', 'Сценарий') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="id">{!! $sortLink('id', 'ID') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="offer_id">{!! $sortLink('offer_id', 'Внешний ID') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="status">{!! $sortLink('status', 'Статус объекта') !!}</th>
                    <th class="px-3 py-2 text-left col-price" data-col="price">{!! $sortLink('price', 'Цена') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="area">{!! $sortLink('area', 'Площадь') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="kitchen_area">{!! $sortLink('kitchen_area', 'Кухня') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="living_area">{!! $sortLink('living_area', 'Жилая') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="city">{!! $sortLink('city', 'Город') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="address">{!! $sortLink('address', 'Адрес') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="deal">{!! $sortLink('deal', 'Сделка') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="category">{!! $sortLink('category', 'Категория') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="rooms">{!! $sortLink('rooms', 'Комнаты') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="rooms_offered">{!! $sortLink('rooms_offered', 'Комнат сдаётся') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="floor">{!! $sortLink('floor', 'Этаж') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="floors_total">{!! $sortLink('floors_total', 'Этажей') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="commission">{!! $sortLink('commission', 'Комиссия') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="deposit">{!! $sortLink('deposit', 'Залог') !!}</th>
                    <th class="px-3 py-2 text-left" data-col="created_at">{!! $sortLink('created_at', 'Создан') !!}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($offers as $row)
                    @php
                        /** @var \App\Models\Offer $offer */
                        $offer = $row['offer'];
                        $health = $row['health'];
                        $scenario = $row['scenario'];
                        $offerStatus = $offer->status();
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
                        <td class="px-3 py-2">
                            @if ($offerStatus)
                                <span class="offer-status-badge" style="background-color: {{ $offerStatus->badgeColor() }}">{{ $offerStatus->label() }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 col-price">{{ $offer->price !== null ? number_format((int) $offer->price, 0, '.', ' ') : '—' }}</td>
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
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $offers->links() }}
    </div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.colVis.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}';

    const statusIcons = {
        ok: `<svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>`,
        warning: `<svg class="w-5 h-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>`,
    };

    const pollTimers = {};
    let dataTable = null;

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
                <td class="px-2 py-1 text-gray-500">${task.id ?? '—'}</td>
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
                        <th class="px-2 py-1">ID</th>
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

    const findOfferRow = (offerId) => document.querySelector(`tr.offer-row[data-offer-id="${offerId}"]`);

    const getTasksContentEl = (offerId) => {
        const offerTr = findOfferRow(offerId);
        if (!offerTr) return null;
        const childRow = offerTr.nextElementSibling;
        if (!childRow || !childRow.classList.contains('offer-tasks-child')) return null;
        return childRow.querySelector('.offer-tasks-content');
    };

    const applyTasks = (offerId, tasks) => {
        const content = getTasksContentEl(offerId);
        if (!content) return;
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
        const content = getTasksContentEl(offerId);
        if (!content) return;
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

    const closeAllTaskChildren = (exceptOfferId = null) => {
        document.querySelectorAll('tr.offer-row').forEach((tr) => {
            const id = tr.dataset.offerId;
            if (exceptOfferId !== null && id === exceptOfferId) return;
            if (!dataTable) return;
            const row = dataTable.row(tr);
            if (row.child.isShown()) {
                row.child.hide();
            }
        });
    };

    const toggleTasks = async (offerId) => {
        const offerTr = findOfferRow(offerId);
        if (!offerTr || !dataTable) return;

        const row = dataTable.row(offerTr);
        if (row.child.isShown()) {
            row.child.hide();
            return;
        }

        closeAllTaskChildren(offerId);
        row.child('<div class="offer-tasks-content text-sm text-gray-500">Загрузка…</div>', 'offer-tasks-child').show();
        await loadTasks(offerId);
    };

    document.querySelectorAll('.offer-toggle-tasks').forEach((btn) => {
        btn.addEventListener('click', async () => {
            await toggleTasks(btn.dataset.offerId);
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

    // --- Column visibility (DataTables), как у агентов ---
    const tableEl = document.getElementById('offers-table');
    if (!tableEl) return;

    if (typeof jQuery === 'undefined' || typeof DataTable === 'undefined') {
        console.error('Offers table: jQuery/DataTables failed to load', {
            jQuery: typeof jQuery,
            DataTable: typeof DataTable,
        });
        return;
    }

    const stateKey = 'dt:offers-list:v1';

    function columnKeys(dt) {
        return dt.columns().header().toArray().map(function (th, index) {
            return th.getAttribute('data-col') || ('col-' + index);
        });
    }

    function loadColumnVisibility() {
        try {
            const raw = window.localStorage.getItem(stateKey);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function saveColumnVisibility(dt) {
        const keys = columnKeys(dt);
        const visibility = {};
        for (let index = 0; index < keys.length; index++) {
            const key = keys[index];
            if (!key || key === 'health') {
                continue;
            }
            visibility[key] = dt.column(index).visible();
        }
        window.localStorage.setItem(stateKey, JSON.stringify(visibility));
    }

    let suppressColumnSave = false;

    function applyColumnVisibility(dt, savedVisibility) {
        if (!savedVisibility) {
            return;
        }
        const keys = columnKeys(dt);
        let changed = false;
        suppressColumnSave = true;
        try {
            for (let index = 0; index < keys.length; index++) {
                const key = keys[index];
                if (!key || key === 'health' || typeof savedVisibility[key] !== 'boolean') {
                    continue;
                }
                if (dt.column(index).visible() !== savedVisibility[key]) {
                    dt.column(index).visible(savedVisibility[key], false);
                    changed = true;
                }
            }
            if (changed) {
                dt.columns.adjust().draw(false);
            }
        } finally {
            suppressColumnSave = false;
        }
    }

    try {
        const savedVisibility = loadColumnVisibility();

        dataTable = new DataTable(tableEl, {
            paging: false,
            info: false,
            searching: false,
            ordering: false,
            autoWidth: false,
            layout: {
                topStart: null,
                topEnd: {
                    buttons: [
                        {
                            extend: 'colvis',
                            text: '☰ Столбцы',
                            className: 'offers-btn-columns',
                            columns: ':not(.no-colvis)',
                        },
                        {
                            text: 'Сбросить столбцы',
                            className: 'offers-btn-reset-columns',
                            action: function (e, dt) {
                                window.localStorage.removeItem(stateKey);
                                dt.columns().visible(true, false);
                                dt.columns.adjust().draw(false);
                            },
                        },
                    ],
                },
                bottomStart: null,
                bottomEnd: null,
            },
            language: {
                emptyTable: 'Нет офферов',
                zeroRecords: 'Нет офферов',
                buttons: {
                    colvis: 'Столбцы',
                    colvisRestore: 'Показать все',
                },
            },
            columnDefs: [
                {
                    targets: 0,
                    className: 'no-colvis',
                },
            ],
            stateSave: false,
        });

        applyColumnVisibility(dataTable, savedVisibility);
    } catch (error) {
        console.error('Offers table: DataTables init failed', error);
        return;
    }

    const toolbar = document.getElementById('offers-table-toolbar');
    const buttonsContainer = tableEl.closest('.dt-container')?.querySelector('.dt-buttons');
    if (toolbar && buttonsContainer) {
        toolbar.appendChild(buttonsContainer);
    }

    dataTable.on('column-visibility.dt', function () {
        if (!suppressColumnSave) {
            saveColumnVisibility(dataTable);
        }
    });
});
</script>
@endpush
