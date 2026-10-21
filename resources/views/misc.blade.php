@extends('layout')

@section('title', 'Прочее')

@section('content')
    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-2xl font-bold mb-6">Прочее</h1>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 mb-6" role="tablist">
            <button type="button" class="misc-tab px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-700 -mb-px" data-tab="birthday">Поздравления с ДР</button>
            <button type="button" class="misc-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 -mb-px" data-tab="discount">Скидка на ДР</button>
            <button type="button" class="misc-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 -mb-px" data-tab="welcome">Автоприветствие</button>
            <button type="button" class="misc-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 -mb-px" data-tab="comments">Комментарии</button>
        </div>

        <div class="misc-panel space-y-8" data-panel="birthday">
            @include('partials.standard-text-field', [
                'fieldId' => 'text-birthday-male',
                'label' => 'Поздравление для мужчин',
                'key' => $keys['birthday_male'],
                'value' => $texts[$keys['birthday_male']] ?? '',
                'placeholders' => $placeholders,
            ])
            @include('partials.standard-text-field', [
                'fieldId' => 'text-birthday-female',
                'label' => 'Поздравление для женщин',
                'key' => $keys['birthday_female'],
                'value' => $texts[$keys['birthday_female']] ?? '',
                'placeholders' => $placeholders,
            ])
        </div>

        <div class="misc-panel hidden space-y-8" data-panel="discount">
            @include('partials.standard-text-field', [
                'fieldId' => 'text-birthday-discount',
                'label' => 'Текст скидки на день рождения',
                'key' => $keys['birthday_discount'],
                'value' => $texts[$keys['birthday_discount']] ?? '',
                'placeholders' => $placeholders,
            ])
        </div>

        <div class="misc-panel hidden space-y-8" data-panel="welcome">
            @include('partials.standard-text-field', [
                'fieldId' => 'text-welcome',
                'label' => 'Текст автоприветствия',
                'key' => $keys['welcome'],
                'value' => $texts[$keys['welcome']] ?? '',
                'placeholders' => $placeholders,
            ])
        </div>

        <div class="misc-panel hidden" data-panel="comments">
            <div class="standard-text-field" data-comment-editor>
                <label for="comment-body" class="block text-sm font-medium mb-2">Текст комментария</label>
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
                        id="comment-body"
                        rows="5"
                        class="w-full border border-gray-300 rounded-2xl px-4 py-3 pr-14 pb-12 text-sm resize-y min-h-[120px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        data-comment-textarea
                    ></textarea>
                    <button
                        type="button"
                        class="absolute bottom-3 right-3 w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center shadow hover:bg-blue-700 disabled:opacity-50"
                        title="Сохранить"
                        data-save-comment
                        aria-label="Сохранить"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-xs min-h-[1rem]" data-comment-feedback></p>
                <input type="hidden" data-editing-comment-id value="">
            </div>

            <div class="mt-6">
                <h2 class="text-sm font-medium mb-2">Созданные комментарии</h2>
                <ul
                    id="standard-comments-list"
                    class="border border-gray-200 rounded-2xl divide-y divide-gray-100 max-h-80 overflow-y-auto bg-gray-50"
                >
                    @forelse ($comments as $comment)
                        <li class="flex items-start gap-3 px-4 py-3 bg-white" data-comment-id="{{ $comment->id }}">
                            <p class="flex-1 text-sm whitespace-pre-wrap break-words" data-comment-body>{{ $comment->body }}</p>
                            <div class="flex gap-1 shrink-0">
                                <button type="button" class="p-1.5 rounded-full text-gray-500 hover:bg-gray-100 hover:text-blue-600" title="Редактировать" data-edit-comment aria-label="Редактировать">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="m2.695 14.762-1.262 3.155a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.886L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" />
                                    </svg>
                                </button>
                                <button type="button" class="p-1.5 rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600" title="Удалить" data-delete-comment aria-label="Удалить">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-sm text-gray-500 text-center" data-comments-empty>Пока нет комментариев</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '{{ csrf_token() }}';

    function setFeedback(el, message, isError) {
        if (!el) return;
        el.textContent = message || '';
        el.className = 'mt-1 text-xs min-h-[1rem] ' + (isError ? 'text-red-600' : 'text-green-600');
    }

    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        textarea.value = before + text + after;
        const pos = start + text.length;
        textarea.focus();
        textarea.setSelectionRange(pos, pos);
    }

    document.querySelectorAll('.misc-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const name = tab.dataset.tab;
            document.querySelectorAll('.misc-tab').forEach(function (t) {
                const active = t.dataset.tab === name;
                t.classList.toggle('border-blue-600', active);
                t.classList.toggle('text-blue-700', active);
                t.classList.toggle('border-transparent', !active);
                t.classList.toggle('text-gray-600', !active);
            });
            document.querySelectorAll('.misc-panel').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.dataset.panel !== name);
            });
        });
    });

    document.querySelectorAll('[data-placeholder-badges]').forEach(function (wrap) {
        const field = wrap.closest('.standard-text-field');
        const textarea = field?.querySelector('textarea');
        if (!textarea) return;
        wrap.querySelectorAll('.placeholder-badge').forEach(function (badge) {
            badge.addEventListener('click', function () {
                insertAtCursor(textarea, badge.dataset.placeholder || '');
            });
        });
    });

    document.querySelectorAll('[data-text-key]').forEach(function (field) {
        const key = field.dataset.textKey;
        const textarea = field.querySelector('[data-standard-textarea]');
        const saveBtn = field.querySelector('[data-save-text]');
        const feedback = field.querySelector('[data-field-feedback]');
        if (!key || !textarea || !saveBtn) return;

        saveBtn.addEventListener('click', async function () {
            saveBtn.disabled = true;
            setFeedback(feedback, 'Сохранение…', false);
            try {
                const response = await fetch('/misc/texts/' + encodeURIComponent(key), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ body: textarea.value }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    const msg = (data.messages && data.messages[0]) || 'Не удалось сохранить';
                    setFeedback(feedback, msg, true);
                    return;
                }
                textarea.value = data.body ?? textarea.value;
                setFeedback(feedback, (data.messages && data.messages[0]) || 'Сохранено', false);
            } catch (e) {
                setFeedback(feedback, 'Ошибка сети', true);
            } finally {
                saveBtn.disabled = false;
            }
        });
    });

    const commentEditor = document.querySelector('[data-comment-editor]');
    const commentTextarea = commentEditor?.querySelector('[data-comment-textarea]');
    const commentSaveBtn = commentEditor?.querySelector('[data-save-comment]');
    const commentFeedback = commentEditor?.querySelector('[data-comment-feedback]');
    const editingIdInput = commentEditor?.querySelector('[data-editing-comment-id]');
    const list = document.getElementById('standard-comments-list');

    function clearEmptyPlaceholder() {
        list?.querySelector('[data-comments-empty]')?.remove();
    }

    function renderCommentItem(comment) {
        const li = document.createElement('li');
        li.className = 'flex items-start gap-3 px-4 py-3 bg-white';
        li.dataset.commentId = String(comment.id);
        li.innerHTML =
            '<p class="flex-1 text-sm whitespace-pre-wrap break-words" data-comment-body></p>' +
            '<div class="flex gap-1 shrink-0">' +
            '<button type="button" class="p-1.5 rounded-full text-gray-500 hover:bg-gray-100 hover:text-blue-600" title="Редактировать" data-edit-comment aria-label="Редактировать">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="m2.695 14.762-1.262 3.155a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.886L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" /></svg>' +
            '</button>' +
            '<button type="button" class="p-1.5 rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600" title="Удалить" data-delete-comment aria-label="Удалить">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>' +
            '</button>' +
            '</div>';
        li.querySelector('[data-comment-body]').textContent = comment.body;
        return li;
    }

    function resetCommentEditor() {
        if (commentTextarea) commentTextarea.value = '';
        if (editingIdInput) editingIdInput.value = '';
    }

    commentSaveBtn?.addEventListener('click', async function () {
        const body = (commentTextarea?.value || '').trim();
        if (!body) {
            setFeedback(commentFeedback, 'Введите текст комментария', true);
            return;
        }

        const editingId = editingIdInput?.value || '';
        const url = editingId ? '/misc/comments/' + editingId : '/misc/comments';
        const method = editingId ? 'PUT' : 'POST';

        commentSaveBtn.disabled = true;
        setFeedback(commentFeedback, 'Сохранение…', false);

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ body: body }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                const msg = (data.messages && data.messages[0])
                    || (data.errors && data.errors.body && data.errors.body[0])
                    || 'Не удалось сохранить';
                setFeedback(commentFeedback, msg, true);
                return;
            }

            clearEmptyPlaceholder();
            if (editingId) {
                const row = list?.querySelector('[data-comment-id="' + editingId + '"]');
                const bodyEl = row?.querySelector('[data-comment-body]');
                if (bodyEl) bodyEl.textContent = data.comment.body;
            } else if (list && data.comment) {
                list.prepend(renderCommentItem(data.comment));
            }

            resetCommentEditor();
            setFeedback(commentFeedback, (data.messages && data.messages[0]) || 'Сохранено', false);
        } catch (e) {
            setFeedback(commentFeedback, 'Ошибка сети', true);
        } finally {
            commentSaveBtn.disabled = false;
        }
    });

    list?.addEventListener('click', async function (event) {
        const editBtn = event.target.closest('[data-edit-comment]');
        const deleteBtn = event.target.closest('[data-delete-comment]');
        const row = event.target.closest('[data-comment-id]');
        if (!row) return;
        const id = row.dataset.commentId;

        if (editBtn) {
            const body = row.querySelector('[data-comment-body]')?.textContent || '';
            if (commentTextarea) commentTextarea.value = body;
            if (editingIdInput) editingIdInput.value = id;
            commentTextarea?.focus();
            setFeedback(commentFeedback, 'Редактирование комментария #' + id, false);
            return;
        }

        if (deleteBtn) {
            if (!confirm('Удалить комментарий?')) return;
            try {
                const response = await fetch('/misc/comments/' + id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert((data.messages && data.messages[0]) || 'Не удалось удалить');
                    return;
                }
                row.remove();
                if (editingIdInput?.value === id) {
                    resetCommentEditor();
                }
                if (list && !list.querySelector('[data-comment-id]')) {
                    const empty = document.createElement('li');
                    empty.className = 'px-4 py-6 text-sm text-gray-500 text-center';
                    empty.dataset.commentsEmpty = '1';
                    empty.textContent = 'Пока нет комментариев';
                    list.appendChild(empty);
                }
            } catch (e) {
                alert('Ошибка сети');
            }
        }
    });
});
</script>
@endpush
