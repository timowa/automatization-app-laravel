<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Models\Offer;
use App\Models\Publication;

final class HasExclusiveTemplateRule implements ExclusiveRule
{
    public function rejection(Offer $offer, ?Publication $source, string $trigger): ?string
    {
        if (! $offer->exclusive) {
            return 'Объект не эксклюзивный';
        }
        if ($source === null) {
            return 'Нет основной публикации';
        }

        return app(ScenarioVkPostTemplateResolver::class)->supports($source->scenario) ? null : 'У основного сценария нет шаблона';
    }
}
