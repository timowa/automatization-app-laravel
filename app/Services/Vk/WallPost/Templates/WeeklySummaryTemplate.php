<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Models\Agent;
use Illuminate\Support\Collection;

class WeeklySummaryTemplate
{
    public function generate(Collection $activeOffers, int $closedCount, Agent $agent): string
    {
        $lines = [
            sprintf('📊 Активные объекты агента %s', $agent->name),
            '',
        ];

        foreach ($activeOffers as $offer) {
            $category = $offer->category()?->label() ?? '';
            $rooms = $offer->rooms > 0 ? sprintf('%d-комн.', $offer->rooms) : '';
            $address = $offer->getAddressFromLocation();
            $price = formatPrice($offer->getPrice());

            $firstLine = trim(sprintf('📍 %s %s, %s', $category, $rooms, $address));
            $lines[] = $firstLine;
            $lines[] = sprintf('💰 %s руб.', $price);
        }

        $lines[] = '';

        if ($closedCount > 0) {
            $lines[] = sprintf('🎉 На этой неделе продано: %d', $closedCount);
            $lines[] = '';
        }

        $lines[] = 'Контакты:';
        $lines[] = sprintf('Агент: %s', $agent->name);
        $lines[] = sprintf('📞 %s', $this->formatPhone($agent->phone));
        $lines[] = '';
        $lines[] = '#брокер_плюс';

        return implode("\n", $lines);
    }

    private function formatPhone(string $phone): string
    {
        return str_starts_with($phone, '7') ? '+' . $phone : $phone;
    }
}
