@extends('layout')

@section('title', 'Агенты')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.2/css/buttons.dataTables.min.css">
    <style>
        .agents-table-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .agents-table-toolbar .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            float: none;
        }

        .agents-table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            scrollbar-width: thin;
            scrollbar-color: #6b7280 #f3f4f6;
        }

        .agents-table-scroll::-webkit-scrollbar {
            height: 10px;
        }

        .agents-table-scroll::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 999px;
        }

        #agents-table {
            width: 100% !important;
            border-collapse: collapse;
        }

        #agents-table thead th {
            background-color: #f9fafb;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        #agents-table.display tbody tr:hover {
            background-color: #f9fafb;
        }

        div.dt-container {
            font-size: 0.875rem;
        }

        .agents-table-toolbar .dt-button {
            margin: 0 !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: 0.375rem !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            line-height: 1.25rem !important;
            box-shadow: none !important;
            cursor: pointer;
        }

        .agents-table-toolbar .dt-button.agents-btn-columns {
            background: #2563eb !important;
            border: 1px solid #1d4ed8 !important;
            color: #fff !important;
        }

        .agents-table-toolbar .dt-button.agents-btn-columns:hover,
        .agents-table-toolbar .dt-button.agents-btn-columns:focus {
            background: #1d4ed8 !important;
            border-color: #1e40af !important;
            color: #fff !important;
        }

        .agents-table-toolbar .dt-button.agents-btn-columns.active {
            background: #1e40af !important;
        }

        .agents-table-toolbar .dt-button.agents-btn-reset-columns {
            background: #f8fafc !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
        }

        .agents-table-toolbar .dt-button.agents-btn-reset-columns:hover,
        .agents-table-toolbar .dt-button.agents-btn-reset-columns:focus {
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

        .agents-sticky-hscroll {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 40;
            display: none;
            align-items: center;
            height: 18px;
            padding: 3px 8px;
            background: #e5e7eb;
            border-top: 1px solid #d1d5db;
        }

        .agents-sticky-hscroll.is-visible {
            display: flex;
        }

        .agents-sticky-hscroll-track {
            position: relative;
            flex: 1 1 auto;
            height: 12px;
            border-radius: 999px;
            background: #d1d5db;
            cursor: pointer;
        }

        .agents-sticky-hscroll-thumb {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            min-width: 48px;
            border-radius: 999px;
            background: #6b7280;
            cursor: grab;
        }

        .agents-sticky-hscroll-thumb:hover,
        .agents-sticky-hscroll-thumb.is-dragging {
            background: #4b5563;
            cursor: grabbing;
        }

        body.has-agents-sticky-hscroll {
            padding-bottom: 18px;
        }
    </style>
@endpush

@section('content')
    <a href="/agents/create" class="inline-block mb-4 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Добавить агента</a>

    <div class="agents-table-toolbar" id="agents-table-toolbar"></div>

    <div class="bg-white rounded shadow agents-table-scroll" id="agents-table-scroll">
        <table id="agents-table" class="min-w-full text-sm display nowrap">
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left">Телефон</th>
                    <th class="px-4 py-2 text-left">ФИО</th>
                    <th class="px-4 py-2 text-left">Поздравления</th>
                    <th class="px-4 py-2 text-center">Токен</th>
                    <th class="px-4 py-2 text-center">Активность</th>
                    <th class="px-4 py-2 text-left">VK ID</th>
                    <th class="px-4 py-2 text-left">Имя</th>
                    <th class="px-4 py-2 text-left">Фамилия</th>
                    <th class="px-4 py-2 text-left">Пол</th>
                    <th class="px-4 py-2 text-left">Дата рожд.</th>
                    <th class="px-4 py-2 text-left">Сем. пол.</th>
                    <th class="px-4 py-2 text-left">Screen Name</th>
                    <th class="px-4 py-2 text-left">Domain</th>
                    <th class="px-4 py-2 text-left">Открыт</th>
                    <th class="px-4 py-2 text-left">Страна</th>
                    <th class="px-4 py-2 text-left">Город</th>
                    <th class="px-4 py-2 text-left">Родной город</th>
                    <th class="px-4 py-2 text-left">Был в сети</th>
                    <th class="px-4 py-2 text-left">Платформа</th>
                    <th class="px-4 py-2 text-left">Подписчики</th>
                    <th class="px-4 py-2 text-left" style="max-width: 200px;">Статус VK</th>
                    <th class="px-4 py-2 text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($agents as $agent)
                    @php
                        $vkUser = $vkUsers[$agent->id] ?? null;
                        $hasVk = $vkUser instanceof \App\Models\VkUser && $vkUser->exists();
                        $agentSetting = $settings[$agent->id] ?? null;
                        $wishHappyBirthday = (bool) ($agentSetting->wish_happy_birthday ?? false);
                        $maleText = (string) ($agentSetting->birthday_wish_male_text ?? '');
                        $femaleText = (string) ($agentSetting->birthday_wish_female_text ?? '');
                        $hasMaleText = $wishHappyBirthday && trim($maleText) !== '';
                        $hasFemaleText = $wishHappyBirthday && trim($femaleText) !== '';
                        $agentStats = $stats[$agent->id] ?? null;
                        $tokenOrder = $hasVk ? ($vkUser->is_token_available ? 2 : 1) : 0;
                        $activityOrder = $agentStats
                            ? ((int) $agentStats->views + (int) $agentStats->reposts + (int) $agentStats->likes + (int) $agentStats->comments)
                            : -1;
                        $birthdayOrder = ($hasMaleText ? 2 : 0) + ($hasFemaleText ? 1 : 0);
                    @endphp
                    <tr
                        class="cursor-pointer hover:bg-gray-50"
                        data-agent-id="{{ $agent->id }}"
                        data-agent-name="{{ $agent->name }}"
                        data-agent-phone="{{ $agent->phone }}"
                        data-wish-birthday="{{ $wishHappyBirthday ? '1' : '0' }}"
                        data-has-male-text="{{ $hasMaleText ? '1' : '0' }}"
                        data-has-female-text="{{ $hasFemaleText ? '1' : '0' }}"
                    >
                        <td class="px-4 py-2" data-order="{{ $agent->phone }}">
                            <a href="/agents/edit/{{ $agent->id }}" class="font-bold">{{ $agent->phone }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $agent->name }}</td>
                        <td class="px-4 py-2 whitespace-nowrap" data-order="{{ $birthdayOrder }}" data-birthday-status>
                            <span class="inline-flex gap-1">
                                <span data-gender-badge="male" class="inline-block px-2 py-1 text-xs font-semibold rounded {{ $hasMaleText ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' }}">М</span>
                                <span data-gender-badge="female" class="inline-block px-2 py-1 text-xs font-semibold rounded {{ $hasFemaleText ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' }}">Ж</span>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center" data-order="{{ $tokenOrder }}">
                            @if ($hasVk && $vkUser->is_token_available)
                                <span class="text-green-600" title="Токен активен">&#10003;</span>
                            @elseif ($hasVk && !$vkUser->is_token_available)
                                <span class="text-yellow-600" title="Токен недоступен">&#33;</span>
                            @endif
                        </td>
                        <td class="px-4 py-2" data-order="{{ $activityOrder }}">
                            @if ($agentStats)
                                <div class="flex items-center gap-3">
                                    <div class="flex flex-col items-center">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Просмотры"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <span class="text-xs font-semibold">{{ $agentStats->views }}</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Репосты"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                        <span class="text-xs font-semibold">{{ $agentStats->reposts }}</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Лайки"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                        <span class="text-xs font-semibold">{{ $agentStats->likes }}</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Комментарии"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        <span class="text-xs font-semibold">{{ $agentStats->comments }}</span>
                                    </div>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->vk_user_id ?? '' }}">
                            @if ($hasVk)
                                <a href="https://vk.com/id{{ $vkUser->vk_user_id }}" target="_blank" class="text-gray-500 hover:underline">
                                    {{ $vkUser->vk_user_id }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $vkUser?->first_name ?? '' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->last_name ?? '' }}</td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->sex ?? -1 }}">{{ \App\Enums\Vk\Sex::labelFor($vkUser?->sex) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->bdate ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->relation ?? -1 }}">{{ \App\Enums\Vk\Relation::labelFor($vkUser?->relation) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->screen_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->domain ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $hasVk ? ($vkUser->is_closed ? 0 : 1) : -1 }}">
                            @if ($hasVk)
                                @if ($vkUser->is_closed)
                                    <span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-200 text-gray-700 rounded">Закрыт</span>
                                @else
                                    <span class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded">Открыт</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $vkUser?->country_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->city_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->home_town ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->last_seen_at ?? 0 }}">
                            {{ $vkUser?->last_seen_at ? date('d.m.Y H:i:s', $vkUser->last_seen_at) : '—' }}
                        </td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->last_seen_platform ?? -1 }}">{{ \App\Enums\Vk\LastSeenPlatform::labelFor($vkUser?->last_seen_platform) ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $vkUser?->followers_count ?? -1 }}">{{ $vkUser?->followers_count ?? '—' }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $vkUser?->status ?? '' }}">
                            {{ $vkUser?->status ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right" data-order="0">
                            <form id="delete-agent-form-{{ $agent->id }}" action="/agents/delete/{{ $agent->id }}" method="POST" class="ajaxForm inline">
                                @csrf
                                <input type="hidden" name="password" value="">
                                <button type="button" class="text-red-600 hover:text-red-800" title="Удалить агента" onclick="deleteAgent('{{ addslashes($agent->name) }}', 'delete-agent-form-{{ $agent->id }}')">&#10007;</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="agents-sticky-hscroll" id="agents-sticky-hscroll" aria-hidden="true" role="scrollbar" aria-orientation="horizontal" aria-controls="agents-table-scroll">
        <div class="agents-sticky-hscroll-track" id="agents-sticky-hscroll-track">
            <div class="agents-sticky-hscroll-thumb" id="agents-sticky-hscroll-thumb"></div>
        </div>
    </div>

    <div
        id="agent-settings-offcanvas"
        class="fixed inset-0 z-50 hidden"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-labelledby="agent-settings-title"
        data-csrf="{{ csrf_token() }}"
    >
        <div data-offcanvas-backdrop class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300"></div>
        <div data-offcanvas-panel class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col bg-white shadow-xl transition-transform duration-300">
            <div class="flex items-start justify-between border-b px-6 py-4">
                <div>
                    <p class="text-sm text-gray-500">Настройки агента</p>
                    <h2 id="agent-settings-title" class="text-lg font-bold"></h2>
                    <p data-agent-phone class="text-sm text-gray-600"></p>
                </div>
                <button type="button" data-offcanvas-close class="text-2xl leading-none text-gray-500 hover:text-gray-800" aria-label="Закрыть">&times;</button>
            </div>
            <form id="agent-settings-form" method="POST" class="flex flex-1 flex-col px-6 py-5 overflow-y-auto">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="wish_happy_birthday" value="1" class="rounded border-gray-300">
                    <span class="text-sm font-medium">Автоматически поздравлять друзей с днём рождения</span>
                </label>

                <div data-birthday-texts class="mt-5 space-y-4 hidden">
                    <div>
                        <label for="birthday_wish_male_text" class="block text-sm font-medium mb-1">Поздравление для мужчин</label>
                        <textarea
                            id="birthday_wish_male_text"
                            name="birthday_wish_male_text"
                            rows="6"
                            maxlength="4096"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Текст сообщения для мужчин"
                        ></textarea>
                    </div>
                    <div>
                        <label for="birthday_wish_female_text" class="block text-sm font-medium mb-1">Поздравление для женщин</label>
                        <textarea
                            id="birthday_wish_female_text"
                            name="birthday_wish_female_text"
                            rows="6"
                            maxlength="4096"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Текст сообщения для женщин"
                        ></textarea>
                    </div>
                </div>

                <p data-settings-feedback class="mt-4 min-h-5 text-sm" role="status"></p>
                <div class="mt-auto pt-6">
                    <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700 disabled:opacity-60">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script type="application/json" id="agent-birthday-texts">@json($birthdayTextsByAgent)</script>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.colVis.min.js"></script>
    <script>
        (function () {
            const tableEl = document.getElementById('agents-table');
            const scrollEl = document.getElementById('agents-table-scroll');
            const stickyScroll = document.getElementById('agents-sticky-hscroll');
            const stickyTrack = document.getElementById('agents-sticky-hscroll-track');
            const stickyThumb = document.getElementById('agents-sticky-hscroll-thumb');
            let syncingScroll = false;
            let dragState = null;

            if (!tableEl || !scrollEl || !stickyScroll || !stickyTrack || !stickyThumb) {
                console.error('Agents table: required DOM nodes are missing');
                return;
            }

            if (typeof jQuery === 'undefined' || typeof DataTable === 'undefined') {
                console.error('Agents table: jQuery/DataTables failed to load', {
                    jQuery: typeof jQuery,
                    DataTable: typeof DataTable,
                });
                return;
            }

            function maxScrollLeft() {
                return Math.max(0, tableEl.scrollWidth - scrollEl.clientWidth);
            }

            function updateStickyScrollbar() {
                const maxScroll = maxScrollLeft();
                const needsScroll = maxScroll > 1;

                if (!needsScroll) {
                    stickyScroll.classList.remove('is-visible');
                    stickyScroll.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('has-agents-sticky-hscroll');
                    return;
                }

                stickyScroll.classList.add('is-visible');
                stickyScroll.setAttribute('aria-hidden', 'false');
                document.body.classList.add('has-agents-sticky-hscroll');

                const trackWidth = stickyTrack.clientWidth;
                const ratio = scrollEl.clientWidth / tableEl.scrollWidth;
                const thumbWidth = Math.max(72, Math.round(trackWidth * ratio));
                const maxThumbLeft = Math.max(0, trackWidth - thumbWidth);
                const thumbLeft = maxScroll === 0
                    ? 0
                    : Math.round((scrollEl.scrollLeft / maxScroll) * maxThumbLeft);

                stickyThumb.style.width = thumbWidth + 'px';
                stickyThumb.style.transform = 'translateX(' + thumbLeft + 'px)';
                stickyScroll.setAttribute('aria-valuenow', String(Math.round(scrollEl.scrollLeft)));
                stickyScroll.setAttribute('aria-valuemin', '0');
                stickyScroll.setAttribute('aria-valuemax', String(Math.round(maxScroll)));
            }

            function setScrollFromThumbLeft(thumbLeft) {
                const maxScroll = maxScrollLeft();
                const trackWidth = stickyTrack.clientWidth;
                const thumbWidth = stickyThumb.offsetWidth;
                const maxThumbLeft = Math.max(0, trackWidth - thumbWidth);
                const clamped = Math.min(Math.max(thumbLeft, 0), maxThumbLeft);
                scrollEl.scrollLeft = maxThumbLeft === 0 ? 0 : (clamped / maxThumbLeft) * maxScroll;
                updateStickyScrollbar();
            }

            scrollEl.addEventListener('scroll', function () {
                if (syncingScroll) {
                    return;
                }
                syncingScroll = true;
                updateStickyScrollbar();
                syncingScroll = false;
            });

            stickyTrack.addEventListener('pointerdown', function (event) {
                if (event.target === stickyThumb) {
                    return;
                }
                const rect = stickyTrack.getBoundingClientRect();
                const thumbWidth = stickyThumb.offsetWidth;
                setScrollFromThumbLeft(event.clientX - rect.left - thumbWidth / 2);
            });

            stickyThumb.addEventListener('pointerdown', function (event) {
                event.preventDefault();
                const rect = stickyTrack.getBoundingClientRect();
                const style = window.getComputedStyle(stickyThumb);
                const matrix = new DOMMatrixReadOnly(style.transform);
                dragState = {
                    pointerId: event.pointerId,
                    startX: event.clientX,
                    startLeft: matrix.m41,
                    trackLeft: rect.left,
                };
                stickyThumb.classList.add('is-dragging');
                stickyThumb.setPointerCapture(event.pointerId);
            });

            stickyThumb.addEventListener('pointermove', function (event) {
                if (!dragState || event.pointerId !== dragState.pointerId) {
                    return;
                }
                const delta = event.clientX - dragState.startX;
                setScrollFromThumbLeft(dragState.startLeft + delta);
            });

            function endDrag(event) {
                if (!dragState || event.pointerId !== dragState.pointerId) {
                    return;
                }
                dragState = null;
                stickyThumb.classList.remove('is-dragging');
            }

            stickyThumb.addEventListener('pointerup', endDrag);
            stickyThumb.addEventListener('pointercancel', endDrag);

            window.addEventListener('resize', updateStickyScrollbar);

            let dataTable;
            try {
                dataTable = new DataTable(tableEl, {
                    paging: false,
                    info: false,
                    searching: false,
                    ordering: true,
                    order: [],
                    autoWidth: false,
                    layout: {
                        topStart: null,
                        topEnd: {
                            buttons: [
                                {
                                    extend: 'colvis',
                                    text: '☰ Столбцы',
                                    className: 'agents-btn-columns',
                                    columns: ':not(.no-colvis)',
                                },
                                {
                                    text: 'Сбросить столбцы',
                                    className: 'agents-btn-reset-columns',
                                    action: function (e, dt) {
                                        dt.state.clear();
                                        dt.columns().visible(true, false);
                                        dt.columns.adjust().draw(false);
                                        window.requestAnimationFrame(updateStickyScrollbar);
                                    },
                                },
                            ],
                        },
                        bottomStart: null,
                        bottomEnd: null,
                    },
                    language: {
                        emptyTable: 'Нет агентов',
                        zeroRecords: 'Нет агентов',
                        buttons: {
                            colvis: 'Столбцы',
                            colvisRestore: 'Показать все',
                        },
                    },
                    columnDefs: [
                        {
                            targets: -1,
                            orderable: false,
                            className: 'no-colvis',
                        },
                    ],
                    stateSave: true,
                    stateDuration: 0,
                });
            } catch (error) {
                console.error('Agents table: DataTables init failed', error);
                return;
            }

            const toolbar = document.getElementById('agents-table-toolbar');
            const buttonsContainer = tableEl.closest('.dt-container')?.querySelector('.dt-buttons');
            if (toolbar && buttonsContainer) {
                toolbar.appendChild(buttonsContainer);
            }

            dataTable.on('column-visibility.dt draw.dt columns-adjust.dt', function () {
                window.requestAnimationFrame(updateStickyScrollbar);
            });

            window.requestAnimationFrame(updateStickyScrollbar);
            window.setTimeout(updateStickyScrollbar, 100);

            // --- settings offcanvas ---
            const root = document.getElementById('agent-settings-offcanvas');
            const backdrop = root.querySelector('[data-offcanvas-backdrop]');
            const panel = root.querySelector('[data-offcanvas-panel]');
            const title = document.getElementById('agent-settings-title');
            const phone = root.querySelector('[data-agent-phone]');
            const form = document.getElementById('agent-settings-form');
            const checkbox = form.querySelector('input[name="wish_happy_birthday"]');
            const textsBlock = form.querySelector('[data-birthday-texts]');
            const maleTextarea = form.querySelector('textarea[name="birthday_wish_male_text"]');
            const femaleTextarea = form.querySelector('textarea[name="birthday_wish_female_text"]');
            const feedback = root.querySelector('[data-settings-feedback]');
            const submitButton = form.querySelector('button[type="submit"]');
            const csrf = root.dataset.csrf;
            const birthdayTextsNode = document.getElementById('agent-birthday-texts');
            let birthdayTextsByAgent = {};
            try {
                birthdayTextsByAgent = JSON.parse(birthdayTextsNode?.textContent || '{}');
            } catch (error) {
                console.error('Agents table: failed to parse birthday texts', error);
            }
            let currentRow = null;
            let hideTimer = null;

            function genderBadgeHtml(label, active) {
                const classes = active
                    ? 'inline-block px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-700'
                    : 'inline-block px-2 py-1 text-xs font-semibold rounded bg-gray-200 text-gray-700';

                return '<span data-gender-badge="' + (label === 'М' ? 'male' : 'female') + '" class="' + classes + '">' + label + '</span>';
            }

            function birthdayStatusHtml(hasMale, hasFemale) {
                return '<span class="inline-flex gap-1">'
                    + genderBadgeHtml('М', hasMale)
                    + genderBadgeHtml('Ж', hasFemale)
                    + '</span>';
            }

            function syncTextsVisibility() {
                if (checkbox.checked) {
                    textsBlock.classList.remove('hidden');
                } else {
                    textsBlock.classList.add('hidden');
                }
            }

            function openOffcanvas(row) {
                currentRow = row;
                title.textContent = row.dataset.agentName || '';
                phone.textContent = row.dataset.agentPhone || '';
                checkbox.checked = row.dataset.wishBirthday === '1';
                const texts = birthdayTextsByAgent[row.dataset.agentId] || { male: '', female: '' };
                maleTextarea.value = texts.male || '';
                femaleTextarea.value = texts.female || '';
                syncTextsVisibility();
                form.action = '/agents/settings/' + row.dataset.agentId;
                feedback.textContent = '';
                feedback.className = 'mt-4 min-h-5 text-sm';

                window.clearTimeout(hideTimer);
                root.classList.remove('hidden');
                root.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(function () {
                    backdrop.classList.remove('opacity-0');
                    backdrop.classList.add('opacity-100');
                    panel.classList.remove('translate-x-full');
                });
                checkbox.focus();
            }

            function closeOffcanvas() {
                backdrop.classList.add('opacity-0');
                backdrop.classList.remove('opacity-100');
                panel.classList.add('translate-x-full');
                window.clearTimeout(hideTimer);
                hideTimer = window.setTimeout(function () {
                    root.classList.add('hidden');
                    root.setAttribute('aria-hidden', 'true');
                }, 300);
            }

            checkbox.addEventListener('change', syncTextsVisibility);

            tableEl.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, label, form')) {
                    return;
                }

                const row = event.target.closest('tr[data-agent-id]');
                if (!row) {
                    return;
                }

                openOffcanvas(row);
            });

            root.querySelector('[data-offcanvas-close]').addEventListener('click', closeOffcanvas);
            backdrop.addEventListener('click', closeOffcanvas);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !root.classList.contains('hidden')) {
                    closeOffcanvas();
                }
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!currentRow) {
                    return;
                }

                const enabled = checkbox.checked;
                const maleText = maleTextarea.value;
                const femaleText = femaleTextarea.value;
                const formData = new FormData();
                formData.append('_token', csrf);
                formData.append('wish_happy_birthday', enabled ? '1' : '0');
                formData.append('birthday_wish_male_text', maleText);
                formData.append('birthday_wish_female_text', femaleText);

                submitButton.disabled = true;
                feedback.textContent = '';
                feedback.className = 'mt-4 min-h-5 text-sm';

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    const data = result.data || {};
                    if (!result.ok || !data.success) {
                        let messages = data.messages || [];
                        if (!messages.length && data.errors) {
                            messages = Object.values(data.errors).flat();
                        }
                        if (!messages.length && data.message) {
                            messages = [data.message];
                        }
                        feedback.textContent = messages.join('\n') || 'Не удалось сохранить настройки';
                        feedback.classList.add('text-red-600');
                        return;
                    }

                    const saved = Boolean(data.wish_happy_birthday);
                    const hasMale = Boolean(data.has_male_text) && saved;
                    const hasFemale = Boolean(data.has_female_text) && saved;
                    currentRow.dataset.wishBirthday = saved ? '1' : '0';
                    currentRow.dataset.hasMaleText = hasMale ? '1' : '0';
                    currentRow.dataset.hasFemaleText = hasFemale ? '1' : '0';
                    checkbox.checked = saved;
                    maleTextarea.value = data.birthday_wish_male_text || '';
                    femaleTextarea.value = data.birthday_wish_female_text || '';
                    birthdayTextsByAgent[currentRow.dataset.agentId] = {
                        male: data.birthday_wish_male_text || '',
                        female: data.birthday_wish_female_text || '',
                    };
                    syncTextsVisibility();
                    const statusCell = currentRow.querySelector('[data-birthday-status]');
                    if (statusCell) {
                        statusCell.innerHTML = birthdayStatusHtml(hasMale, hasFemale);
                        statusCell.setAttribute('data-order', String((hasMale ? 2 : 0) + (hasFemale ? 1 : 0)));
                    }
                    feedback.textContent = (data.messages && data.messages[0]) || 'Настройки сохранены';
                    feedback.classList.add('text-green-700');
                })
                .catch(function () {
                    feedback.textContent = 'Ошибка запроса';
                    feedback.classList.add('text-red-600');
                })
                .finally(function () {
                    submitButton.disabled = false;
                });
            });
        })();
    </script>
@endpush
