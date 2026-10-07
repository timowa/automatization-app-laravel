@extends('layout')

@section('title', 'История версий — '.config('app.name'))

@section('content')
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">История версий</h1>
                <p class="text-sm text-gray-500 mt-1">Текущая версия: <span class="font-semibold text-gray-800">{{ $currentVersion }}</span></p>
            </div>
            <a href="/agents" class="text-sm text-blue-600 hover:underline">← К агентам</a>
        </div>

        <ol class="space-y-6">
            @forelse ($releases as $release)
                <li class="border-l-4 border-blue-500 pl-4">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h2 class="text-lg font-semibold">v{{ $release['version'] }}</h2>
                        @if (!empty($release['date']))
                            <span class="text-sm text-gray-400">{{ $release['date'] }}</span>
                        @endif
                    </div>
                    <ul class="mt-2 list-disc list-inside space-y-1">
                        @foreach ($release['changes'] as $change)
                            <li class="text-sm text-gray-700">{{ $change }}</li>
                        @endforeach
                    </ul>
                </li>
            @empty
                <li class="text-gray-500">Записей пока нет.</li>
            @endforelse
        </ol>
    </div>
@endsection
