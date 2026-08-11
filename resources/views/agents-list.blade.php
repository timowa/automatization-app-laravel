
@extends('layout')

@section('title', 'Агенты')

@section('content')
    <a href="/agents/create" class="inline-block mb-4 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Добавить агента</a>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">Телефон</th>
                    <th class="px-4 py-2 text-left">VK</th>
                    <th class="px-4 py-2 text-left">ФИО</th>
                    <th class="px-4 py-2 text-center">Токен</th>
                    <th class="px-4 py-2 text-right">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($agents as $agent)
                    @php
                        $vkUser = $vkUsers[$agent->id] ?? null;
                        $hasVk = $vkUser instanceof \App\Models\VkUser;
                    @endphp
                    <tr>
                        <td class="px-4 py-2"><a href="/agents/edit/{{ $agent->id }}" class="font-bold">{{ $agent->phone }}</a></td>
                        <td class="px-4 py-2">
                            @if ($hasVk)
                                <a href="https://vk.com/id{{ $vkUser->vk_user_id }}" target="_blank" class="text-gray-500 hover:underline">{{ $vkUser->vk_user_id }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $agent->name }}</td>
                        <td class="px-4 py-2 text-center">
                            @if ($hasVk)
                                @if ($vkUser->is_token_available)
                                    <span class="text-green-600" title="Токен активен">✓</span>
                                @else
                                    <span class="text-yellow-600" title="Токен недоступен">!</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <form id="delete-agent-form-{{ $agent->id }}" action="/agents/delete/{{ $agent->id }}" method="POST" class="ajaxForm inline">
                                @csrf
                                <input type="hidden" name="password" value="">
                                <button type="button" class="text-red-600 hover:text-red-800" title="Удалить агента" onclick="deleteAgent('{{ addslashes($agent->name) }}', 'delete-agent-form-{{ $agent->id }}')">×</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
