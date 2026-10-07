@php
    /** @var string $productVersion */
    /** @var list<array{version: string, date: string, changes: list<string>}> $recentReleases */
@endphp
<div class="relative" id="product-version-menu">
    <button
        type="button"
        id="product-version-toggle"
        class="text-sm text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-50"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="product-version-dropdown"
    >
        {{ $productVersion }}
    </button>
    <div
        id="product-version-dropdown"
        class="hidden absolute right-0 mt-1 w-80 max-w-[90vw] bg-white border border-gray-200 rounded-lg shadow-lg z-50 p-3 text-left"
        role="menu"
    >
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Последние версии</div>
        <ul class="space-y-3 max-h-80 overflow-y-auto">
            @forelse ($recentReleases as $release)
                <li class="border-b border-gray-100 last:border-0 pb-2 last:pb-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-semibold text-sm text-gray-800">v{{ $release['version'] }}</span>
                        @if (!empty($release['date']))
                            <span class="text-xs text-gray-400 shrink-0">{{ $release['date'] }}</span>
                        @endif
                    </div>
                    <ul class="mt-1 space-y-0.5">
                        @foreach (array_slice($release['changes'], 0, 2) as $change)
                            <li class="text-xs text-gray-600 leading-snug">{{ $change }}</li>
                        @endforeach
                        @if (count($release['changes']) > 2)
                            <li class="text-xs text-gray-400">…</li>
                        @endif
                    </ul>
                </li>
            @empty
                <li class="text-xs text-gray-500">История версий пока пуста.</li>
            @endforelse
        </ul>
        <div class="mt-3 pt-2 border-t border-gray-100">
            <a href="{{ route('changelog') }}" class="text-sm text-blue-600 hover:underline">Все версии →</a>
        </div>
    </div>
</div>
