
@extends('layout')

@section('title', 'Посты')

@section('content')
    <h2 class="text-xl font-bold mb-4">Опубликованные посты</h2>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">Код</th>
                    <th class="px-4 py-2 text-left">Сценарий</th>
                    <th class="px-4 py-2 text-left">Дата публикации</th>
                    <th class="px-4 py-2 text-left">Статистика</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row->code }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <span>{{ $row->scenario }}</span>
                                @if ($row->post_url)
                                    <a href="{{ $row->post_url }}" target="_blank" class="text-blue-600 hover:text-blue-800" title="Открыть пост">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2">{{ $row->posted_at }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-col items-center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Просмотры">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <span class="text-xs font-semibold">{{ $row->views }}</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Репосты">
                                        <polyline points="17 1 21 5 17 9"/>
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                        <polyline points="7 23 3 19 7 15"/>
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                    <span class="text-xs font-semibold">{{ $row->reposts }}</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Лайки">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                    <span class="text-xs font-semibold">{{ $row->likes }}</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Комментарии">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    <span class="text-xs font-semibold">{{ $row->comments }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
@endsection
