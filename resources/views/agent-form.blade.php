
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

        @if (!empty($statsByOffer))
            <hr class="my-6">
            <h3 class="text-lg font-bold mb-4">Статистика публикаций</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left border">Оффер / Сценарий</th>
                            <th class="px-4 py-2 text-center border">Просмотры</th>
                            <th class="px-4 py-2 text-center border">Репосты</th>
                            <th class="px-4 py-2 text-center border">Лайки</th>
                            <th class="px-4 py-2 text-center border">Комментарии</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statsByOffer as $code => $offerData)
                            <tr class="bg-gray-100 font-semibold">
                                <td class="px-4 py-2 border">{{ $code }}</td>
                                <td class="px-4 py-2 text-center border">{{ $offerData['total']['views'] }}</td>
                                <td class="px-4 py-2 text-center border">{{ $offerData['total']['reposts'] }}</td>
                                <td class="px-4 py-2 text-center border">{{ $offerData['total']['likes'] }}</td>
                                <td class="px-4 py-2 text-center border">{{ $offerData['total']['comments'] }}</td>
                            </tr>
                            @foreach ($scenarioOrder as $scenarioValue)
                                @php
                                    $scenarioStats = $offerData['scenarios'][$scenarioValue] ?? null;
                                @endphp
                                @if ($scenarioStats)
                                    <tr>
                                        <td class="px-4 py-2 border pl-8">{{ \App\Enums\ScenarioType::from($scenarioValue)->label() }}</td>
                                        <td class="px-4 py-2 text-center border">{{ $scenarioStats['views'] }}</td>
                                        <td class="px-4 py-2 text-center border">{{ $scenarioStats['reposts'] }}</td>
                                        <td class="px-4 py-2 text-center border">{{ $scenarioStats['likes'] }}</td>
                                        <td class="px-4 py-2 text-center border">{{ $scenarioStats['comments'] }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

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

@push('scripts')
    <script>
        (function () {
            const PREFIX = '+7 ';

            function formatPhone(value) {
                let digits = value.replace(/\D/g, '');
                if (digits.length && digits[0] === '8') {
                    digits = '7' + digits.slice(1);
                }
                if (digits.length && digits[0] !== '7') {
                    digits = '7' + digits;
                }
                digits = digits.slice(0, 11);

                let result = PREFIX;
                const rest = digits.slice(1);
                if (rest.length > 0) result += rest.slice(0, 3);
                if (rest.length > 3) result += ' ' + rest.slice(3, 6);
                if (rest.length > 6) result += '-' + rest.slice(6, 8);
                if (rest.length > 8) result += '-' + rest.slice(8, 10);

                return result;
            }

            document.querySelectorAll('input[name="phone"]').forEach(function (input) {
                input.value = formatPhone(input.value);
                input.addEventListener('input', function () {
                    const formatted = formatPhone(input.value);
                    if (input.value !== formatted) {
                        input.value = formatted;
                    }
                });
            });
        })();
    </script>
@endpush
