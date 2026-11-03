@extends('layout')
@section('title', 'Эксклюзивные публикации')
@section('content')
    <a href="{{ route('offers.index', ['code' => $offer->code, 'status' => '']) }}" class="text-blue-600">← К объекту {{ $offer->code }}</a>
    <h2 class="text-xl font-bold my-4">Эксклюзивные публикации — {{ $offer->code }}</h2>
    <p class="mb-4">{{ $status }}</p>
    <a class="text-blue-600" href="{{ route('offers.exclusive', $offer) }}">Обновить результаты</a>
    @if($offer->exclusive_skip_reason && $previousRun)
        <p class="mb-4"><a class="text-blue-600" href="{{ route('offers.exclusive', $previousRun->offer_id) }}#run-{{ $previousRun->id }}">Предыдущий запуск — {{ formatUiDate($previousRun->started_at) }}</a></p>
    @endif
    @if(session('message'))<p role="status" class="mb-4">{{ session('message') }}</p>@endif
    <section class="bg-white rounded shadow p-4 mb-4">
        <h3 class="font-semibold">Основная публикация</h3>
        @if($offer->publication)
            <p>{{ $offer->publication->scenario->label() }}</p>
            @foreach($offer->publication->tasks as $task)
                <p>{{ $task->type->label() }} — {{ $task->status->label() }}
                    @if($task->error) — {{ app(\App\Services\PublicationTaskErrorPresenter::class)->present($task->error) }} @endif
                </p>
            @endforeach
            <a class="text-blue-600" href="{{ route('offers.index', ['code' => $offer->code, 'status' => '']) }}">Повтор основных задач доступен в списке офферов</a>
        @else
            <p>Без основного сценария</p>
        @endif
    </section>
    @if($offer->exclusiveRuns->isNotEmpty())
        <form method="POST" action="{{ route('offers.exclusive.retry', $offer) }}" class="mb-4">
            @csrf
            <button class="px-3 py-2 border rounded">Повторить ошибки эксклюзивных публикаций</button>
        </form>
    @endif
    @foreach($offer->exclusiveRuns as $run)
        @php
            $finished = $run->publications->filter(fn ($p) => $p->tasks->every(fn ($t) => in_array($t->status->value, ['success', 'failed'], true)))->count();
        @endphp
        <section id="run-{{ $run->id }}" class="bg-white rounded shadow p-4 mb-4">
            <h3 class="font-semibold">Запуск №{{ $run->id }} — {{ formatUiDate($run->started_at) }}</h3>
            <p>{{ $run->trigger === 'transition' ? 'Объект стал эксклюзивным' : 'Завершены основные задачи' }}. Шаблон: {{ $run->scenario->label() }}.</p>
            <p>Завершено публикаций: {{ $finished }} из {{ count($run->recipient_ids) }}.</p>
            @forelse($run->publications as $publication)
                <div class="border rounded p-3 mt-3">
                    <h4 class="font-semibold">{{ $publication->agent?->name ?? 'Агент недоступен' }}</h4>
                    @if($publication->agent?->vkUser)
                        <a class="text-blue-600" href="https://vk.com/id{{ $publication->agent->vkUser->vk_user_id }}" target="_blank" rel="noopener noreferrer">Страница агента</a>
                    @endif
                    @foreach($publication->tasks as $task)
                        <p class="my-1">{{ $task->type->label() }} — {{ $task->status->label() }}
                            @if($task->error) — {{ app(\App\Services\PublicationTaskErrorPresenter::class)->present($task->error) }} @endif
                            @if($task->type === \App\Enums\PublicationTaskType::VK_POST && $task->external_id)
                                @php($post = $task->wallPost)
                                @if($post)<a class="text-blue-600" href="https://vk.com/wall{{ $post->getFullId() }}" target="_blank" rel="noopener noreferrer">Открыть пост</a>@endif
                            @elseif($task->type === \App\Enums\PublicationTaskType::VK_STORY && $task->external_id)
                                <a class="text-blue-600" href="https://vk.com/story{{ $task->external_id }}" target="_blank" rel="noopener noreferrer">Открыть историю</a>
                            @endif
                        </p>
                    @endforeach
                    @if($publication->tasks->contains(fn ($t) => $t->status === \App\Enums\PublicationTaskStatus::FAILED))
                        <form method="POST" action="{{ route('offers.exclusive.retry', $offer) }}">
                            @csrf
                            <input type="hidden" name="publication_id" value="{{ $publication->id }}">
                            <button class="text-blue-600 underline">Повторить ошибки этого агента</button>
                        </form>
                    @endif
                </div>
            @empty
                <p>Нет подходящих агентов.</p>
            @endforelse
        </section>
    @endforeach
@endsection

@if(str_starts_with($status, 'Ожидает') || $offer->exclusiveRuns->contains(fn ($run) => in_array($run->status, ['pending', 'processing'], true)))
    @push('scripts')
        <script>setTimeout(() => window.location.reload(), 10000);</script>
    @endpush
@endif
