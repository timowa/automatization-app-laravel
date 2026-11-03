<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Actions\CreatePublicationAction;
use App\Models\ExclusivePublicationRun;
use App\Models\Offer;
use App\Models\Publication;
use App\Scenarios\Scenarios\ExclusiveScenario\ExclusiveScenario;
use App\Scenarios\TaskDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ExclusivePublicationService
{
    /** @return list<class-string<ExclusiveRule>> */
    public function rules(): array
    {
        return [HasExclusiveTemplateRule::class, ExclusiveCooldownRule::class];
    }

    public function start(Offer $offer, ?Publication $source, string $trigger): ?ExclusivePublicationRun
    {
        return DB::transaction(function () use ($offer, $source, $trigger): ?ExclusivePublicationRun {
            DB::table('exclusive_object_locks')->insertOrIgnore(['code' => $offer->code]);
            DB::table('exclusive_object_locks')->where('code', $offer->code)->lockForUpdate()->first();
            $key = $trigger.':'.($trigger === 'transition' ? $offer->id : $source?->id);
            if ($existing = ExclusivePublicationRun::where('trigger_key', $key)->first()) {
                return $existing;
            }
            foreach ($this->rules() as $rule) {
                if ($reason = app($rule)->rejection($offer, $source, $trigger)) {
                    $offer->update(['exclusive_skip_reason' => $reason]);
                    Log::channel('job')->info('Эксклюзивная публикация пропущена', ['offer_id' => $offer->id, 'reason' => $reason]);

                    return null;
                }
            }
            $recipients = app(ExclusiveRecipientResolver::class)->resolve($offer);
            $run = ExclusivePublicationRun::create([
                'offer_id' => $offer->id, 'code' => $offer->code, 'trigger' => $trigger,
                'trigger_key' => $key, 'source_publication_id' => $source->id,
                'scenario' => $source->scenario, 'recipient_ids' => $recipients,
                'started_at' => now(), 'status' => $recipients === [] ? 'empty' : 'pending',
                'completed_at' => $recipients === [] ? now() : null,
            ]);
            $offer->update(['exclusive_skip_reason' => null]);
            Log::channel('job')->info('Запуск эксклюзивных публикаций создан', ['offer_id' => $offer->id, 'run_id' => $run->id, 'recipients' => count($recipients)]);

            return $run;
        });
    }

    public function expand(ExclusivePublicationRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $run = ExclusivePublicationRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'pending') {
                return;
            }
            $offer = Offer::findOrFail($run->offer_id);
            foreach ($run->recipient_ids as $agentId) {
                if (! $run->publications()->where('agent_id', $agentId)->exists()) {
                    app(CreatePublicationAction::class)->execute($offer, new ExclusiveScenario, $agentId, $run->id);
                }
            }
            $run->update(['status' => 'processing']);
        });
        foreach ($run->publications()->pluck('id') as $id) {
            app(TaskDispatcher::class)->dispatch($id);
        }
    }
}
