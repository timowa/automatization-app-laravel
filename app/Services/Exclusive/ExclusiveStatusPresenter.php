<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Models\Offer;

final class ExclusiveStatusPresenter
{
    public function label(Offer $offer): string
    {
        if (! $offer->exclusive) {
            return '—';
        }
        $run = $offer->exclusiveRuns->sortByDesc('id')->first();
        if ($run !== null) {
            return match ($run->status) {
                'empty' => 'Нет подходящих агентов',
                'completed' => 'Завершено',
                'failed' => 'Завершено с ошибками',
                default => 'Выполняется',
            };
        }
        if ($offer->exclusive_skip_reason !== null) {
            return 'Пропущено: '.$offer->exclusive_skip_reason;
        }
        if ($offer->skip_reason !== null) {
            return 'Не запускалось: '.$offer->skip_reason;
        }

        return $offer->exclusive_transition ? 'Ожидает запуска' : 'Ожидает основных задач';
    }
}
