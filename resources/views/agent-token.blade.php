
@extends('layout')

@section('title', 'Обновление токена')

@section('content')
    @php
        $link = 'https://oauth.vk.com/authorize?' . http_build_query([
        'client_id' => 4775211,
        'display' => 'page',
        'redirect_uri' => 'https://oauth.vk.com/blank.html',
        'scope' => 203374166,
        'response_type' => 'token',
        'v' => '5.131',
            'revoke' => 1
    ]);
    @endphp

    <div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
        <h5 class="text-lg font-bold mb-4">Обновление токена агента</h5>

        <form action="/agents/update-token/{{ $agent->id }}" method="POST" class="ajaxForm flex gap-2 mb-6">
            @csrf
            <input type="text" name="link" class="flex-1 border rounded px-3 py-2" placeholder="Вставьте ссылку сюда">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Сохранить</button>
        </form>

        <div class="text-sm text-gray-600 space-y-2">
            <p><b>Шаг 1.</b> Перейдите по <a href="{{ $link }}" target="_blank" class="text-blue-600 hover:underline"><b>ссылке</b></a> и нажмите «Разрешить».</p>
            <p><b>Шаг 2.</b> Скопируйте ссылку из адресной строки браузера.</p>
            <p><b>Шаг 3.</b> Вставьте ссылку в поле выше и нажмите «Сохранить».</p>
        </div>
    </div>
@endsection
