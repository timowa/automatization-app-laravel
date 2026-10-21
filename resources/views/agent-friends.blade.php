@extends('layout')

@section('title', 'Друзья — ' . $agent->name)

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

        #agent-friends-table {
            width: 100% !important;
            border-collapse: collapse;
        }

        #agent-friends-table thead th {
            background-color: #f9fafb;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        #agent-friends-table.display tbody tr:hover {
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

        body.has-friends-sticky-hscroll {
            padding-bottom: 18px;
        }

        .city-filter-control {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 0.25rem;
            box-sizing: border-box;
            height: 38px;
            padding: 0 0.5rem;
            overflow: hidden;
            cursor: text;
        }

        .city-filter-chips {
            display: contents;
        }

        .city-filter-chip {
            display: inline-flex;
            align-items: center;
            flex: 0 1 auto;
            gap: 0.15rem;
            max-width: 9rem;
            height: 1.5rem;
            margin: 0;
            padding: 0 0.2rem 0 0.45rem;
            border-radius: 0.25rem;
            background: #e8f0fe;
            color: #1e3a8a;
            font-size: 0.75rem;
            line-height: 1.5rem;
            white-space: nowrap;
        }

        .city-filter-chip-label {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .city-filter-chip button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            padding: 0;
            border: 0;
            background: transparent;
            color: #1d4ed8;
            font-size: 0.9rem;
            line-height: 1;
            cursor: pointer;
        }

        .city-filter-input {
            flex: 1 0 1.75rem;
            min-width: 1.75rem;
            width: 0;
            height: 1.5rem;
            border: 0;
            padding: 0;
            background: transparent;
            font-size: 0.875rem;
            line-height: 1.5rem;
            outline: none;
        }

        .city-filter-suggestions {
            position: absolute;
            z-index: 30;
            left: 0;
            right: 0;
            top: calc(100% + 0.25rem);
            max-height: 16rem;
            overflow-y: auto;
            margin: 0;
            padding: 0.25rem;
            list-style: none;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        .city-filter-suggestions [role="option"] {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            border-radius: 0.375rem;
            padding: 0.45rem 0.6rem;
            color: #1f2937;
            cursor: pointer;
        }

        .city-filter-suggestions [role="option"].is-active,
        .city-filter-suggestions [role="option"]:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .friends-scroll-top {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            min-width: 4.5rem;
            padding: 0.65rem 1rem;
            border: 1px solid #1d4ed8;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.15);
            cursor: pointer;
        }

        .friends-scroll-top.is-visible {
            display: inline-flex;
        }

        .friends-scroll-top:hover,
        .friends-scroll-top:focus {
            background: #1d4ed8;
        }

        body.has-friends-sticky-hscroll .friends-scroll-top {
            bottom: 2.25rem;
        }
    </style>
@endpush

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold">Друзья: {{ $agent->name }} ({{ $friends->count() }})</h2>
        <div class="flex gap-4 text-sm">
            <a href="/agents/edit/{{ $agent->id }}" class="text-gray-600 hover:underline">К агенту</a>
            <a href="/agents" class="text-gray-600 hover:underline">К списку агентов</a>
        </div>
    </div>

    <form method="GET" action="/agents/{{ $agent->id }}/friends" class="mb-4 grid gap-3 rounded bg-white p-4 shadow md:grid-cols-2 xl:grid-cols-4">
        <label class="block text-sm md:col-span-2">
            <span class="mb-1 block font-medium">Поиск</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Имя, фамилия, ник, телефон, адрес" class="w-full rounded border border-gray-300 px-3 py-2">
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Пол</span>
            <select name="sex" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                @foreach (\App\Enums\Vk\Sex::cases() as $sex)
                    <option value="{{ $sex->value }}" @selected($filters['sex'] === $sex->value)>{{ $sex->label() }}</option>
                @endforeach
            </select>
        </label>

        <div class="relative block text-sm" id="city-filter">
            <div class="mb-1 flex items-center justify-between gap-2">
                <span class="font-medium">Город</span>
                <button type="button" id="city-filter-reset" class="text-xs font-medium text-blue-600 hover:underline">Сбросить город</button>
            </div>
            <div id="city-filter-control" class="city-filter-control rounded border border-gray-300 bg-white">
                <div id="city-filter-chips" class="city-filter-chips"></div>
                <input
                    type="text"
                    id="city-filter-input"
                    class="city-filter-input"
                    autocomplete="off"
                    placeholder="Введите или выберите город"
                    aria-autocomplete="list"
                    aria-controls="city-filter-suggestions"
                    aria-expanded="false"
                >
            </div>
            <ul id="city-filter-suggestions" class="city-filter-suggestions" role="listbox" hidden></ul>
            <div id="city-filter-values">
                @foreach ($filters['city'] as $selectedCity)
                    <input type="hidden" name="city[]" value="{{ $selectedCity }}">
                @endforeach
            </div>
            <script type="application/json" id="city-filter-data">@json(['cities' => $cities->values(), 'selected' => array_values($filters['city'])])</script>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Страна</span>
            <select name="country" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                @foreach ($countries as $country)
                    <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Страница</span>
            <select name="page" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                <option value="active" @selected($filters['page'] === 'active')>Активна</option>
                @foreach (\App\Enums\Vk\Deactivated::cases() as $pageStatus)
                    <option value="{{ $pageStatus->value }}" @selected($filters['page'] === $pageStatus->value)>{{ $pageStatus->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Профиль</span>
            <select name="closed" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                <option value="0" @selected($filters['closed'] === 0)>Открыт</option>
                <option value="1" @selected($filters['closed'] === 1)>Закрыт</option>
            </select>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Родной город</span>
            <select name="home_town" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                @foreach ($hometowns as $homeTown)
                    <option value="{{ $homeTown }}" @selected($filters['home_town'] === $homeTown)>{{ $homeTown }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Род занятий</span>
            <select name="occupation" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                @foreach (\App\Enums\Vk\OccupationType::cases() as $occupation)
                    <option value="{{ $occupation->value }}" @selected($filters['occupation'] === $occupation->value)>{{ $occupation->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium">Семейное положение</span>
            <select name="relation" class="w-full rounded border border-gray-300 bg-white px-3 py-2">
                <option value="">Все</option>
                @foreach (\App\Enums\Vk\Relation::cases() as $relation)
                    <option value="{{ $relation->value }}" @selected($filters['relation'] === $relation->value)>{{ $relation->label() }}</option>
                @endforeach
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Применить</button>
            <a href="/agents/{{ $agent->id }}/friends" class="rounded bg-gray-200 px-4 py-2 hover:bg-gray-300">Сбросить</a>
        </div>
    </form>

    <p class="mb-3 text-sm text-gray-700">Дата последнего обновления {{ $friendsSyncedAtLabel }}</p>

    <div class="agents-table-toolbar" id="agent-friends-table-toolbar"></div>

    <div class="bg-white rounded shadow agents-table-scroll" id="agent-friends-table-scroll">
        <table id="agent-friends-table" class="min-w-full text-sm display nowrap">
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left" data-col="photo">Фото</th>
                    <th class="px-4 py-2 text-left" data-col="id">ID</th>
                    <th class="px-4 py-2 text-left" data-col="first-name">Имя</th>
                    <th class="px-4 py-2 text-left" data-col="last-name">Фамилия</th>
                    <th class="px-4 py-2 text-left" data-col="page">Страница</th>
                    <th class="px-4 py-2 text-left" data-col="profile">Профиль</th>
                    <th class="px-4 py-2 text-left" data-col="nickname">Никнейм</th>
                    <th class="px-4 py-2 text-left" data-col="sex">Пол</th>
                    <th class="px-4 py-2 text-left" data-col="bdate">Дата рождения</th>
                    <th class="px-4 py-2 text-left" data-col="relation">Семейное положение</th>
                    <th class="px-4 py-2 text-left" data-col="domain">Адрес страницы</th>
                    <th class="px-4 py-2 text-left" data-col="city">Город</th>
                    <th class="px-4 py-2 text-left" data-col="country">Страна</th>
                    <th class="px-4 py-2 text-left" data-col="home-town">Родной город</th>
                    <th class="px-4 py-2 text-left" data-col="occupation">Род занятий</th>
                    <th class="px-4 py-2 text-left" data-col="mobile">Мобильный телефон</th>
                    <th class="px-4 py-2 text-left" data-col="home-phone">Домашний телефон</th>
                    <th class="px-4 py-2 text-left" data-col="has-mobile">Есть мобильный</th>
                    <th class="px-4 py-2 text-left" data-col="online">Онлайн</th>
                    <th class="px-4 py-2 text-left" data-col="last-seen">Был в сети</th>
                    <th class="px-4 py-2 text-left" data-col="platform">Платформа</th>
                    <th class="px-4 py-2 text-left" data-col="timezone">Часовой пояс</th>
                    <th class="px-4 py-2 text-left" data-col="university">Вуз</th>
                    <th class="px-4 py-2 text-left" data-col="faculty">Факультет</th>
                    <th class="px-4 py-2 text-left" data-col="graduation">Год выпуска</th>
                    <th class="px-4 py-2 text-left" data-col="universities">Университеты</th>
                    <th class="px-4 py-2 text-left" data-col="status">Статус</th>
                    <th class="px-4 py-2 text-left" data-col="photo-50">Фото 50</th>
                    <th class="px-4 py-2 text-left" data-col="photo-200">Фото 200</th>
                    <th class="px-4 py-2 text-left" data-col="photo-id">ID фото</th>
                    <th class="px-4 py-2 text-left" data-col="has-photo">Есть фото</th>
                    <th class="px-4 py-2 text-left" data-col="blacklist">В чёрном списке</th>
                    <th class="px-4 py-2 text-left" data-col="followers">Подписчики</th>
                    <th class="px-4 py-2 text-left" data-col="verified">Подтверждена</th>
                    <th class="px-4 py-2 text-left" data-col="maiden-name">Девичья фамилия</th>
                    <th class="px-4 py-2 text-left" data-col="partner">Партнёр</th>
                    <th class="px-4 py-2 text-left" data-col="schools">Школы</th>
                    <th class="px-4 py-2 text-left" data-col="career">Карьера</th>
                    <th class="px-4 py-2 text-left" data-col="site">Сайт</th>
                    <th class="px-4 py-2 text-left" data-col="education-form">Форма обучения</th>
                    <th class="px-4 py-2 text-left" data-col="education-status">Статус учёбы</th>
                    <th class="px-4 py-2 text-left" data-col="lists">Списки</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($friends as $friend)
                    @php
                        $photo100 = $friend->safePhotoUrl('photo_100');
                        $photo50 = $friend->safePhotoUrl('photo_50');
                        $photo200 = $friend->safePhotoUrl('photo_200_orig');
                        $profileUrl = $friend->profileUrl();
                        $universities = $friend->universitiesForDisplay();
                        $schools = $friend->schoolsForDisplay();
                        $career = $friend->careerForDisplay();
                        $pageOrder = match ($friend->deactivated) {
                            'deleted' => 2,
                            'banned' => 1,
                            default => 0,
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-2" data-order="{{ $photo100 ? 1 : 0 }}">
                            @if ($photo100)
                                <img src="{{ $photo100 }}" alt="Фото" width="40" height="40" class="h-10 w-10 rounded object-cover">
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2" data-order="{{ $friend->user_id }}">
                            <a href="{{ $profileUrl }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $friend->user_id }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->first_name) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->last_name) }}</td>
                        <td class="px-4 py-2" data-order="{{ $pageOrder }}">{{ $friend->pageStatusLabel() }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->is_closed === null ? -1 : (int) $friend->is_closed }}">{{ $friend->profileVisibilityLabel() }}</td>
                        <td class="px-4 py-2">{{ $friend->nicknameForDisplay() }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->sex ?? -1 }}">{{ \App\Enums\Vk\Sex::labelFor($friend->sex) ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->bdate ?? '' }}">{{ $friend->bdateForDisplay() }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->relation ?? -1 }}">{{ \App\Enums\Vk\Relation::labelFor($friend->relation) ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($friend->domain)
                                <a href="{{ $profileUrl }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $friend->domain }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->city_name) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->country_name) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->home_town) }}</td>
                        <td class="px-4 py-2">{{ $friend->occupationForDisplay() }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->mobile_phone) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->home_phone) }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->has_mobile === null ? -1 : (int) $friend->has_mobile }}">{{ $friend->yesNoLabel('has_mobile') }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->online === null ? -1 : (int) $friend->online }}">{{ $friend->yesNoLabel('online') }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->last_seen_at ?? 0 }}">{{ $friend->lastSeenForDisplay() }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->last_seen_platform ?? -1 }}">{{ \App\Enums\Vk\LastSeenPlatform::labelFor($friend->last_seen_platform) ?? '—' }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->timezone ?? -100 }}">{{ $friend->timezoneForDisplay() }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->university_name) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->faculty_name) }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->graduation ?? 0 }}">{{ $friend->textOrDash($friend->graduation) }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $universities }}">{{ $universities }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $friend->status ?? '' }}">{{ $friend->textOrDash($friend->status) }}</td>
                        <td class="px-4 py-2">
                            @if ($photo50)
                                <img src="{{ $photo50 }}" alt="Фото 50" width="40" height="40" class="h-10 w-10 rounded object-cover">
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($photo200)
                                <img src="{{ $photo200 }}" alt="Фото 200" width="40" height="40" class="h-10 w-10 rounded object-cover">
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->photo_id) }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->has_photo === null ? -1 : (int) $friend->has_photo }}">{{ $friend->yesNoLabel('has_photo') }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->blacklisted_by_me === null ? -1 : (int) $friend->blacklisted_by_me }}">{{ $friend->yesNoLabel('blacklisted_by_me') }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->followers_count ?? -1 }}">{{ $friend->textOrDash($friend->followers_count) }}</td>
                        <td class="px-4 py-2" data-order="{{ $friend->verified === null ? -1 : (int) $friend->verified }}">{{ $friend->yesNoLabel('verified') }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->maiden_name) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->relation_partner_name) }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $schools }}">{{ $schools }}</td>
                        <td class="px-4 py-2 truncate max-w-xs" title="{{ $career }}">{{ $career }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->site) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->education_form) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->education_status) }}</td>
                        <td class="px-4 py-2">{{ $friend->textOrDash($friend->friend_list_names) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" id="friends-scroll-top" class="friends-scroll-top" hidden>Вверх</button>

    <div class="agents-sticky-hscroll" id="agent-friends-sticky-hscroll" aria-hidden="true" role="scrollbar" aria-orientation="horizontal" aria-controls="agent-friends-table-scroll">
        <div class="agents-sticky-hscroll-track" id="agent-friends-sticky-hscroll-track">
            <div class="agents-sticky-hscroll-thumb" id="agent-friends-sticky-hscroll-thumb"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.colVis.min.js"></script>
    <script>
        (function () {
            const root = document.getElementById('city-filter');
            const dataEl = document.getElementById('city-filter-data');
            const input = document.getElementById('city-filter-input');
            const control = document.getElementById('city-filter-control');
            const chipsEl = document.getElementById('city-filter-chips');
            const suggestionsEl = document.getElementById('city-filter-suggestions');
            const valuesEl = document.getElementById('city-filter-values');
            const resetButton = document.getElementById('city-filter-reset');
            const form = root ? root.closest('form') : null;
            const scrollTopButton = document.getElementById('friends-scroll-top');

            if (root && dataEl && control && input && chipsEl && suggestionsEl && valuesEl && resetButton && form) {
                let payload = { cities: [], selected: [] };
                try {
                    payload = JSON.parse(dataEl.textContent || '{}');
                } catch (error) {
                    console.error('City filter: invalid data', error);
                }

                const knownCities = Array.isArray(payload.cities) ? payload.cities.map(String) : [];
                let selected = Array.isArray(payload.selected) ? payload.selected.map(String) : [];
                let activeIndex = -1;
                let skipPendingCity = false;

                function sameCity(left, right) {
                    return left.toLocaleLowerCase('ru') === right.toLocaleLowerCase('ru');
                }

                function canonicalCity(text) {
                    const trimmed = text.trim();
                    const known = knownCities.find(function (city) {
                        return sameCity(city, trimmed);
                    });

                    return known || trimmed;
                }

                function renderValues() {
                    valuesEl.replaceChildren();
                    selected.forEach(function (city) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'city[]';
                        hidden.value = city;
                        valuesEl.appendChild(hidden);
                    });
                }

                function renderChips() {
                    chipsEl.replaceChildren();
                    selected.forEach(function (city) {
                        const chip = document.createElement('span');
                        chip.className = 'city-filter-chip';

                        const label = document.createElement('span');
                        label.className = 'city-filter-chip-label';
                        label.textContent = city;

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.setAttribute('aria-label', 'Убрать город ' + city);
                        remove.textContent = '×';
                        remove.addEventListener('click', function () {
                            selected = selected.filter(function (item) {
                                return !sameCity(item, city);
                            });
                            render();
                            input.focus();
                        });

                        chip.append(label, remove);
                        chipsEl.appendChild(chip);
                    });
                    input.placeholder = selected.length > 0 ? '' : 'Введите или выберите город';
                }

                function suggestionItems(query) {
                    const needle = query.trim().toLocaleLowerCase('ru');
                    const matches = knownCities.filter(function (city) {
                        if (selected.some(function (item) { return sameCity(item, city); })) {
                            return false;
                        }

                        return needle === '' || city.toLocaleLowerCase('ru').includes(needle);
                    }).slice(0, 8);

                    const trimmed = query.trim();
                    const alreadyKnown = trimmed !== '' && knownCities.some(function (city) {
                        return sameCity(city, trimmed);
                    });
                    const alreadySelected = trimmed !== '' && selected.some(function (city) {
                        return sameCity(city, trimmed);
                    });

                    return {
                        matches: matches,
                        custom: !alreadyKnown && !alreadySelected && trimmed !== '' ? trimmed : null,
                    };
                }

                function hideSuggestions() {
                    suggestionsEl.hidden = true;
                    suggestionsEl.replaceChildren();
                    input.setAttribute('aria-expanded', 'false');
                    activeIndex = -1;
                }

                function showSuggestions() {
                    const items = suggestionItems(input.value);
                    const options = items.matches.slice();
                    if (items.custom !== null) {
                        options.push(items.custom);
                    }

                    suggestionsEl.replaceChildren();
                    if (options.length === 0) {
                        hideSuggestions();
                        return;
                    }

                    options.forEach(function (city, index) {
                        const option = document.createElement('li');
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.setAttribute('role', 'option');
                        button.dataset.city = items.custom !== null && index === options.length - 1 && city === items.custom
                            ? canonicalCity(city)
                            : city;
                        button.textContent = items.custom !== null && index === options.length - 1 && !knownCities.some(function (known) {
                            return sameCity(known, city);
                        })
                            ? 'Добавить «' + city + '»'
                            : city;
                        if (index === activeIndex) {
                            button.classList.add('is-active');
                        }
                        button.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            addCity(button.dataset.city || city);
                        });
                        option.appendChild(button);
                        suggestionsEl.appendChild(option);
                    });

                    suggestionsEl.hidden = false;
                    input.setAttribute('aria-expanded', 'true');
                }

                function addCity(city, options) {
                    const value = canonicalCity(city);
                    if (value === '' || selected.some(function (item) { return sameCity(item, value); })) {
                        input.value = '';
                        hideSuggestions();
                        return;
                    }

                    if (selected.length >= 20) {
                        hideSuggestions();
                        return;
                    }

                    selected.push(value);
                    input.value = '';
                    render();
                    if (options && options.silent) {
                        hideSuggestions();
                        return;
                    }

                    input.focus();
                    showSuggestions();
                }

                function render() {
                    renderChips();
                    renderValues();
                }

                function commitInput() {
                    const pending = input.value.trim();
                    if (pending === '') {
                        return;
                    }
                    addCity(pending);
                }

                control.addEventListener('mousedown', function (event) {
                    if (event.target.closest('button')) {
                        return;
                    }

                    if (event.target !== input) {
                        event.preventDefault();
                        input.focus();
                    }

                    showSuggestions();
                });
                input.addEventListener('input', function () {
                    activeIndex = -1;
                    showSuggestions();
                });
                input.addEventListener('focus', showSuggestions);
                input.addEventListener('keydown', function (event) {
                    const options = Array.from(suggestionsEl.querySelectorAll('[role="option"]'));
                    if (event.key === 'ArrowDown' && options.length > 0) {
                        event.preventDefault();
                        activeIndex = Math.min(activeIndex + 1, options.length - 1);
                        showSuggestions();
                        return;
                    }
                    if (event.key === 'ArrowUp' && options.length > 0) {
                        event.preventDefault();
                        activeIndex = Math.max(activeIndex - 1, 0);
                        showSuggestions();
                        return;
                    }
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        if (activeIndex >= 0 && options[activeIndex]) {
                            addCity(options[activeIndex].dataset.city || options[activeIndex].textContent || '');
                            return;
                        }
                        commitInput();
                        return;
                    }
                    if (event.key === 'Escape') {
                        hideSuggestions();
                        return;
                    }
                    if (event.key === 'Backspace' && input.value === '' && selected.length > 0) {
                        selected.pop();
                        render();
                    }
                });
                document.addEventListener('click', function (event) {
                    if (!root.contains(event.target)) {
                        hideSuggestions();
                    }
                });
                resetButton.addEventListener('click', function () {
                    skipPendingCity = true;
                    selected = [];
                    input.value = '';
                    render();
                    hideSuggestions();
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                    skipPendingCity = false;
                });
                form.addEventListener('submit', function (event) {
                    if (skipPendingCity || input.value.trim() === '') {
                        return;
                    }

                    event.preventDefault();
                    addCity(input.value, { silent: true });
                    form.submit();
                });

                render();
            }

            if (scrollTopButton) {
                const showAfter = 300;

                function updateScrollTopButton() {
                    const visible = window.scrollY > showAfter;
                    scrollTopButton.hidden = !visible;
                    scrollTopButton.classList.toggle('is-visible', visible);
                }

                scrollTopButton.addEventListener('click', function () {
                    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
                });
                window.addEventListener('scroll', updateScrollTopButton, { passive: true });
                updateScrollTopButton();
            }
        })();


        (function () {
            const tableEl = document.getElementById('agent-friends-table');
            const scrollEl = document.getElementById('agent-friends-table-scroll');
            const stickyScroll = document.getElementById('agent-friends-sticky-hscroll');
            const stickyTrack = document.getElementById('agent-friends-sticky-hscroll-track');
            const stickyThumb = document.getElementById('agent-friends-sticky-hscroll-thumb');
            let syncingScroll = false;
            let dragState = null;

            if (!tableEl || !scrollEl || !stickyScroll || !stickyTrack || !stickyThumb) {
                console.error('Friends table: required DOM nodes are missing');
                return;
            }

            if (typeof jQuery === 'undefined' || typeof DataTable === 'undefined') {
                console.error('Friends table: jQuery/DataTables failed to load', {
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
                    document.body.classList.remove('has-friends-sticky-hscroll');
                    return;
                }

                stickyScroll.classList.add('is-visible');
                stickyScroll.setAttribute('aria-hidden', 'false');
                document.body.classList.add('has-friends-sticky-hscroll');

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

            const stateKey = 'dt:agent-friends:{{ $agent->id }}:v3';
            const filtersKey = 'dt:agent-friends-filters:{{ $agent->id }}:v1';
            // Старые ключи могли сохранить видимость со сдвигом (tHead.cells без скрытых столбцов).
            window.localStorage.removeItem('dt:agent-friends:{{ $agent->id }}:v1');
            window.localStorage.removeItem('dt:agent-friends:{{ $agent->id }}:v2');
            const hiddenByDefaultKeys = [
                'home-phone', 'has-mobile', 'platform', 'timezone', 'faculty', 'graduation', 'universities',
                'photo-50', 'photo-200', 'photo-id', 'has-photo', 'blacklist', 'followers', 'verified',
                'maiden-name', 'partner', 'schools', 'career', 'site', 'education-form', 'education-status', 'lists',
            ];

            // DataTables 2 убирает скрытые th из DOM — ключи только через API.
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
                    if (!key) {
                        continue;
                    }
                    visibility[key] = dt.column(index).visible();
                }
                window.localStorage.setItem(stateKey, JSON.stringify(visibility));
            }

            let suppressColumnSave = false;

            function applyColumnVisibility(dt, savedVisibility) {
                const keys = columnKeys(dt);
                let changed = false;
                suppressColumnSave = true;
                try {
                    for (let index = 0; index < keys.length; index++) {
                        const key = keys[index];
                        let visible = hiddenByDefaultKeys.indexOf(key) === -1;
                        if (savedVisibility && typeof savedVisibility[key] === 'boolean') {
                            visible = savedVisibility[key];
                        }
                        if (dt.column(index).visible() !== visible) {
                            dt.column(index).visible(visible, false);
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

            const filtersForm = document.querySelector('form[action="/agents/{{ $agent->id }}/friends"]');
            if (filtersForm) {
                filtersForm.addEventListener('submit', function () {
                    const data = new FormData(filtersForm);
                    const saved = {};
                    data.forEach(function (value, key) {
                        if (!Object.prototype.hasOwnProperty.call(saved, key)) {
                            saved[key] = value;
                            return;
                        }
                        if (!Array.isArray(saved[key])) {
                            saved[key] = [saved[key]];
                        }
                        saved[key].push(value);
                    });
                    window.localStorage.setItem(filtersKey, JSON.stringify(saved));
                });

                const resetLink = filtersForm.querySelector('a[href="/agents/{{ $agent->id }}/friends"]');
                if (resetLink) {
                    resetLink.addEventListener('click', function () {
                        window.localStorage.removeItem(filtersKey);
                    });
                }

                if (!window.location.search || window.location.search === '?') {
                    try {
                        const savedFilters = JSON.parse(window.localStorage.getItem(filtersKey) || 'null');
                        if (savedFilters && typeof savedFilters === 'object') {
                            const params = new URLSearchParams();
                            Object.keys(savedFilters).forEach(function (key) {
                                const value = savedFilters[key];
                                if (Array.isArray(value)) {
                                    value.forEach(function (item) {
                                        params.append(key, item);
                                    });
                                } else if (value !== null && value !== undefined && String(value) !== '') {
                                    params.set(key, String(value));
                                }
                            });
                            const query = params.toString();
                            if (query) {
                                window.location.replace(filtersForm.action + '?' + query);
                                return;
                            }
                        }
                    } catch (error) {
                        console.error('Friends filters restore failed', error);
                    }
                }
            }

            let dataTable;
            try {
                const savedVisibility = loadColumnVisibility();

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
                                        window.localStorage.removeItem(stateKey);
                                        applyColumnVisibility(dt, null);
                                        window.requestAnimationFrame(updateStickyScrollbar);
                                    },
                                },
                            ],
                        },
                        bottomStart: null,
                        bottomEnd: null,
                    },
                    language: {
                        emptyTable: 'Друзей не найдено',
                        zeroRecords: 'Друзей не найдено',
                        buttons: {
                            colvis: 'Столбцы',
                            colvisRestore: 'Показать все',
                        },
                    },
                    stateSave: false,
                });

                applyColumnVisibility(dataTable, savedVisibility);
            } catch (error) {
                console.error('Friends table: DataTables init failed', error);
                return;
            }

            const toolbar = document.getElementById('agent-friends-table-toolbar');
            const buttonsContainer = tableEl.closest('.dt-container')?.querySelector('.dt-buttons');
            if (toolbar && buttonsContainer) {
                toolbar.appendChild(buttonsContainer);
            }

            dataTable.on('column-visibility.dt', function () {
                if (!suppressColumnSave) {
                    saveColumnVisibility(dataTable);
                }
                window.requestAnimationFrame(updateStickyScrollbar);
            });
            dataTable.on('draw.dt columns-adjust.dt', function () {
                window.requestAnimationFrame(updateStickyScrollbar);
            });

            window.requestAnimationFrame(updateStickyScrollbar);
            window.setTimeout(updateStickyScrollbar, 100);
        })();
    </script>
@endpush
