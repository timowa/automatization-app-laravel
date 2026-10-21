@php
    /** @var string $fieldId */
    /** @var string $label */
    /** @var string $key */
    /** @var string $value */
    /** @var list<array{key: string, label: string}> $placeholders */
@endphp
<div class="standard-text-field" data-text-key="{{ $key }}">
    <label for="{{ $fieldId }}" class="block text-sm font-medium mb-2">{{ $label }}</label>
    <div class="flex flex-wrap gap-2 mb-2" data-placeholder-badges>
        @foreach ($placeholders as $placeholder)
            <button
                type="button"
                class="placeholder-badge px-2.5 py-1 text-xs font-medium rounded-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"
                data-placeholder="{{ '{'.'{'.$placeholder['key'].'}'.'}' }}"
            >{{ $placeholder['label'] }}</button>
        @endforeach
    </div>
    <div class="relative">
        <textarea
            id="{{ $fieldId }}"
            rows="8"
            class="w-full border border-gray-300 rounded-2xl px-4 py-3 pr-14 pb-12 text-sm resize-y min-h-[140px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            data-standard-textarea
        >{{ $value }}</textarea>
        <button
            type="button"
            class="absolute bottom-3 right-3 w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center shadow hover:bg-blue-700 disabled:opacity-50"
            title="Сохранить"
            data-save-text
            aria-label="Сохранить"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>
    <p class="mt-1 text-xs min-h-[1rem]" data-field-feedback></p>
</div>
