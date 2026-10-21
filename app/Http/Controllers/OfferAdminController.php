<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RetryOfferFailedTasksAction;
use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Enums\PublicationTaskStatus;
use App\Models\Agent;
use App\Models\Offer;
use App\Services\OfferHealthStatusResolver;
use App\Services\PublicationTaskErrorPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OfferAdminController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const SORTABLE = [
        'health' => 'offers.skip_reason',
        'code' => 'offers.code',
        'agent' => 'agents.name',
        'scenario' => 'publications.scenario',
        'id' => 'offers.id',
        'offer_id' => 'offers.offer_id',
        'status' => 'offers.status',
        'price' => 'offers.price',
        'area' => 'offers.area',
        'kitchen_area' => 'offers.kitchen_area',
        'living_area' => 'offers.living_area',
        'city' => 'offers.city',
        'address' => 'offers.location',
        'deal' => 'offers.deal',
        'category' => 'offers.category',
        'rooms' => 'offers.rooms',
        'rooms_offered' => 'offers.rooms_offered',
        'floor' => 'offers.floor',
        'floors_total' => 'offers.floors_total',
        'commission' => 'offers.commission',
        'deposit' => 'offers.deposit',
        'created_at' => 'offers.created_at',
    ];

    public function index(Request $request, OfferHealthStatusResolver $healthResolver): View
    {
        $sort = (string) $request->input('sort', 'id');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! array_key_exists($sort, self::SORTABLE)) {
            $sort = 'id';
            $dir = 'desc';
        }

        $query = Offer::query()
            ->select('offers.*')
            ->leftJoin('agents', 'agents.id', '=', 'offers.agent_id')
            ->leftJoin('publications', 'publications.offer_id', '=', 'offers.id')
            ->with(['agent', 'publication.tasks']);

        if ($request->filled('agent_id')) {
            $query->where('offers.agent_id', (int) $request->input('agent_id'));
        }

        if ($request->filled('city')) {
            $query->where('offers.city', (int) $request->input('city'));
        }

        if ($request->filled('deal')) {
            $query->where('offers.deal', (int) $request->input('deal'));
        }

        if ($request->filled('status')) {
            $query->where('offers.status', (int) $request->input('status'));
        }

        if ($request->filled('code')) {
            $query->where('offers.code', 'like', '%'.$request->string('code')->toString().'%');
        }

        $query->orderBy(self::SORTABLE[$sort], $dir);
        if ($sort !== 'id') {
            $query->orderByDesc('offers.id');
        }

        $offers = $query->paginate(50)->withQueryString();

        $rows = $offers->getCollection()->map(function (Offer $offer) use ($healthResolver): array {
            $health = $healthResolver->resolve($offer);

            return [
                'offer' => $offer,
                'health' => $health,
                'scenario' => $offer->publication?->scenario?->label() ?? '',
            ];
        });

        $offers->setCollection($rows);

        $filters = [
            'agent_id' => $request->input('agent_id'),
            'city' => $request->input('city'),
            'deal' => $request->input('deal'),
            'status' => $request->input('status'),
            'code' => $request->input('code'),
        ];

        return view('offers-list', [
            'offers' => $offers,
            'agents' => Agent::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'cities' => City::cases(),
            'deals' => Deal::cases(),
            'statuses' => OfferStatus::cases(),
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'sortUrl' => function (string $column) use ($filters, $sort, $dir): string {
                $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
                if ($sort !== $column) {
                    $nextDir = 'asc';
                }

                return '/offers?'.http_build_query(array_filter([
                    ...$filters,
                    'sort' => $column,
                    'dir' => $nextDir,
                ], fn ($value) => $value !== null && $value !== ''));
            },
        ]);
    }

    public function tasks(Offer $offer, PublicationTaskErrorPresenter $errorPresenter): JsonResponse
    {
        $offer->load(['publication.tasks']);

        return response()->json(['tasks' => $this->serializeTasks($offer, $errorPresenter)]);
    }

    public function status(
        Offer $offer,
        OfferHealthStatusResolver $healthResolver,
        PublicationTaskErrorPresenter $errorPresenter,
    ): JsonResponse {
        $offer->load(['publication.tasks']);
        $health = $healthResolver->resolve($offer);

        return response()->json([
            'status' => $health['status'],
            'tooltip' => $health['tooltip'],
            'can_retry' => $health['can_retry'],
            'tasks' => $this->serializeTasks($offer, $errorPresenter),
        ]);
    }

    public function retry(
        Offer $offer,
        RetryOfferFailedTasksAction $action,
        PublicationTaskErrorPresenter $errorPresenter,
    ): JsonResponse {
        try {
            $result = $action->execute($offer);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $offer->refresh()->load(['publication.tasks']);

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'tooltip' => $result['tooltip'],
            'can_retry' => $result['can_retry'],
            'retried' => $result['retried'],
            'tasks' => $this->serializeTasks($offer, $errorPresenter),
        ]);
    }

    /**
     * @return list<array{id: int, title: string, status: string, status_label: string, tooltip: string, error: ?string, finished_at: string}>
     */
    private function serializeTasks(Offer $offer, PublicationTaskErrorPresenter $errorPresenter): array
    {
        return ($offer->publication?->tasks ?? collect())
            ->sortBy('id')
            ->values()
            ->map(function ($task) use ($errorPresenter): array {
                $statusLabel = $this->taskStatusLabel($task->status);
                $tooltip = match ($task->status) {
                    PublicationTaskStatus::FAILED => $errorPresenter->present($task->error),
                    PublicationTaskStatus::SUCCESS => 'Успешно',
                    default => $statusLabel,
                };

                return [
                    'id' => $task->id,
                    'title' => $task->type->label(),
                    'status' => $task->status->value,
                    'status_label' => $statusLabel,
                    'tooltip' => $tooltip,
                    'error' => $task->error,
                    'finished_at' => formatUiDate($task->updated_at ?? $task->created_at),
                ];
            })
            ->all();
    }

    private function taskStatusLabel(PublicationTaskStatus $status): string
    {
        return match ($status) {
            PublicationTaskStatus::WAITING => 'Ожидает',
            PublicationTaskStatus::PENDING => 'В очереди',
            PublicationTaskStatus::QUEUED => 'В очереди',
            PublicationTaskStatus::PROCESSING => 'Выполняется',
            PublicationTaskStatus::SUCCESS => 'Успешно',
            PublicationTaskStatus::FAILED => 'Ошибка',
        };
    }
}
