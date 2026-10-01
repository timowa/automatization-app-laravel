
@extends('layout')

@section('title', 'Агенты')

@section('content')
    <a href="/agents/create" class="inline-block mb-4 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Добавить агента</a>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
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
                        $wishHappyBirthday = (bool) ($settings[$agent->id]->wish_happy_birthday ?? false);
                    @endphp
                    <tr
                        class="cursor-pointer hover:bg-gray-50"
                        data-agent-id="{{ $agent->id }}"
                        data-agent-name="{{ $agent->name }}"
                        data-agent-phone="{{ $agent->phone }}"
                        data-wish-birthday="{{ $wishHappyBirthday ? '1' : '0' }}"
                    >
                        <td class="px-4 py-2">
                            <a href="/agents/edit/{{ $agent->id }}" class="font-bold">{{ $agent->phone }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $agent->name }}</td>
                        <td class="px-4 py-2 whitespace-nowrap" data-birthday-status>
                            @if ($wishHappyBirthday)
                                <span class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded">Вкл</span>
                            @else
                                <span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-200 text-gray-700 rounded">Выкл</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($hasVk && $vkUser->is_token_available)
                                <span class="text-green-600" title="Токен активен">&#10003;</span>
                            @elseif ($hasVk && !$vkUser->is_token_available)
                                <span class="text-yellow-600" title="Токен недоступен">&#33;</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @php
                                $agentStats = $stats[$agent->id] ?? null;
                            @endphp
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
                        <td class="px-4 py-2">
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
                        <td class="px-4 py-2">{{ \App\Enums\Vk\Sex::labelFor($vkUser?->sex) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->bdate ?? '—' }}</td>
                        <td class="px-4 py-2">{{ \App\Enums\Vk\Relation::labelFor($vkUser?->relation) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->screen_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->domain ?? '—' }}</td>
                        <td class="px-4 py-2">
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
                        <td class="px-4 py-2">
                            {{ $vkUser?->last_seen_at ? date('d.m.Y H:i:s', $vkUser->last_seen_at) : '—' }}
                        </td>
                        <td class="px-4 py-2">{{ \App\Enums\Vk\LastSeenPlatform::labelFor($vkUser?->last_seen_platform) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $vkUser?->followers_count ?? '—' }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $vkUser?->status ?? '' }}">
                            {{ $vkUser?->status ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right">
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
            <form id="agent-settings-form" method="POST" class="flex flex-1 flex-col px-6 py-5">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="wish_happy_birthday" value="1" class="rounded border-gray-300">
                    <span class="text-sm font-medium">Автоматически поздравлять друзей с днём рождения</span>
                </label>
                <p data-settings-feedback class="mt-4 min-h-5 text-sm" role="status"></p>
                <div class="mt-auto pt-6">
                    <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700 disabled:opacity-60">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const root = document.getElementById('agent-settings-offcanvas');
            const backdrop = root.querySelector('[data-offcanvas-backdrop]');
            const panel = root.querySelector('[data-offcanvas-panel]');
            const title = document.getElementById('agent-settings-title');
            const phone = root.querySelector('[data-agent-phone]');
            const form = document.getElementById('agent-settings-form');
            const checkbox = form.querySelector('input[name="wish_happy_birthday"]');
            const feedback = root.querySelector('[data-settings-feedback]');
            const submitButton = form.querySelector('button[type="submit"]');
            const csrf = root.dataset.csrf;
            let currentRow = null;
            let hideTimer = null;

            function birthdayBadge(enabled) {
                if (enabled) {
                    return '<span class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded">Вкл</span>';
                }

                return '<span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-200 text-gray-700 rounded">Выкл</span>';
            }

            function openOffcanvas(row) {
                currentRow = row;
                title.textContent = row.dataset.agentName || '';
                phone.textContent = row.dataset.agentPhone || '';
                checkbox.checked = row.dataset.wishBirthday === '1';
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

            document.querySelector('table')?.addEventListener('click', function (event) {
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
                const formData = new FormData();
                formData.append('_token', csrf);
                formData.append('wish_happy_birthday', enabled ? '1' : '0');

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
                    currentRow.dataset.wishBirthday = saved ? '1' : '0';
                    checkbox.checked = saved;
                    const statusCell = currentRow.querySelector('[data-birthday-status]');
                    if (statusCell) {
                        statusCell.innerHTML = birthdayBadge(saved);
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
