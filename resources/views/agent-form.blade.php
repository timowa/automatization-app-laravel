
@extends('layout')

@section('title', $agent->exists ? 'Редактировать агента' : 'Добавить агента')

@section('content')
    @php
        $isCreate = !$agent->exists;
        $formAction = $isCreate ? '/agents' : '/agents/save/' . $agent->id;
        $submitLabel = $isCreate ? 'Создать' : 'Сохранить';
    @endphp

    <div class="max-w-xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-xl font-bold mb-4">{{ $isCreate ? 'Добавить агента' : 'Агент ' . $agent->name }}</h2>

        <form action="{{ $formAction }}" method="POST" class="ajaxForm">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Телефон</label>
                <input type="tel" name="phone" value="{{ $agent->phone }}" placeholder="+7 XXX XXX-XX-XX" class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">ФИО</label>
                <input type="text" name="name" value="{{ $agent->name }}" class="w-full border rounded px-3 py-2">
            </div>

            @if ($vkUser && $vkUser->exists)
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">VK профиль</label>
                    <input type="text" value="{{ $vkUser->domain }} ({{ $vkUser->vk_user_id }})" readonly class="w-full border bg-gray-50 rounded px-3 py-2">
                </div>
            @endif

            @if (!$isCreate)
                <a href="/agents/change-token/{{ $agent->id }}" class="block w-full text-center mb-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Обновить токен</a>
            @endif

            <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700">{{ $submitLabel }}</button>
        </form>

        <a href="/agents" class="block text-center mt-4 text-gray-600 hover:underline">К списку агентов</a>

        @if (!$isCreate)
            <hr class="my-6">
            <form id="delete-agent-form" action="/agents/delete/{{ $agent->id }}" method="POST" class="ajaxForm">
                @csrf
                <input type="hidden" name="password" value="">
                <button type="button" class="w-full py-2 bg-red-600 text-white rounded hover:bg-red-700" onclick="deleteAgent('{{ addslashes($agent->name) }}', 'delete-agent-form')">Удалить агента</button>
            </form>
        @endif
    </div>
@endsection
