<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StandardTextKey;
use App\Models\StandardText;
use Illuminate\Support\Facades\DB;

final class StandardTextService
{
    public function body(StandardTextKey $key): ?string
    {
        $raw = DB::table('standard_texts')
            ->where('key', $key->value)
            ->value('body');

        if (! is_string($raw)) {
            return null;
        }

        $body = trim($raw);

        return $body === '' ? null : $body;
    }

    public function upsert(StandardTextKey $key, string $body): StandardText
    {
        $text = StandardText::query()->firstOrNew(['key' => $key]);
        $text->body = $body;
        $text->save();

        return $text;
    }

    /**
     * @return array<string, string>
     */
    public function allBodiesByKey(): array
    {
        return StandardText::query()
            ->get()
            ->mapWithKeys(fn (StandardText $text) => [
                $text->key->value => (string) ($text->body ?? ''),
            ])
            ->all();
    }
}
