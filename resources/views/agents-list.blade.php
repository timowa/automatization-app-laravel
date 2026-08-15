
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
                    <th class="px-4 py-2 text-center">Токен</th>
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
                    @endphp
                    <tr>
                        <td class="px-4 py-2">
                            <a href="/agents/edit/{{ $agent->id }}" class="font-bold">{{ $agent->phone }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $agent->name }}</td>
                        <td class="px-4 py-2 text-center">
                            @if ($hasVk && $vkUser->is_token_available)
                                <span class="text-green-600" title="Токен активен">&#10003;</span>
                            @elseif ($hasVk && !$vkUser->is_token_available)
                                <span class="text-yellow-600" title="Токен недоступен">&#33;</span>
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
@endsection
