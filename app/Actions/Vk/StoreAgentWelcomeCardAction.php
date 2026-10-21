<?php

declare(strict_types=1);

namespace App\Actions\Vk;

use App\Models\Agent;
use App\Models\AgentWelcomeCard;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

final class StoreAgentWelcomeCardAction
{
    public const THUMB_MAX_WIDTH = 800;

    /**
     * @return array{
     *     path: string,
     *     thumb_path: string,
     *     absolute_original: string,
     *     absolute_thumb: string,
     *     extension: string
     * }
     */
    public function writeLocalFiles(Agent $agent, UploadedFile $file): array
    {
        $extension = $this->normalizeExtension($file);
        $token = str_replace('.', '', uniqid((string) $agent->id, true));
        $originalPath = 'agent-cards/tmp_'.$token.'_card.'.$extension;
        $thumbPath = 'agent-cards/tmp_'.$token.'_card_thumb.'.$extension;

        Storage::disk('public')->makeDirectory('agent-cards');
        Storage::disk('public')->put($originalPath, $file->getContent());

        $absoluteOriginal = Storage::disk('public')->path($originalPath);
        $absoluteThumb = Storage::disk('public')->path($thumbPath);
        $this->createThumb($absoluteOriginal, $absoluteThumb);

        Log::channel('vk-images')->info('Локальные файлы визитки сохранены (временные)', [
            'agent_id' => $agent->id,
            'path' => $originalPath,
            'thumb_path' => $thumbPath,
            'original_size' => is_file($absoluteOriginal) ? filesize($absoluteOriginal) : null,
            'thumb_size' => is_file($absoluteThumb) ? filesize($absoluteThumb) : null,
            'original_image_info' => is_file($absoluteOriginal) ? @getimagesize($absoluteOriginal) : null,
            'thumb_image_info' => is_file($absoluteThumb) ? @getimagesize($absoluteThumb) : null,
        ]);

        return [
            'path' => $originalPath,
            'thumb_path' => $thumbPath,
            'absolute_original' => $absoluteOriginal,
            'absolute_thumb' => $absoluteThumb,
            'extension' => $extension,
        ];
    }

    /**
     * @param  array{path: string, thumb_path: string, extension: string}  $paths
     */
    public function persist(Agent $agent, array $paths, int $mediaId): AgentWelcomeCard
    {
        $finalPath = 'agent-cards/agent_'.$agent->id.'_card.'.$paths['extension'];
        $finalThumbPath = 'agent-cards/agent_'.$agent->id.'_card_thumb.'.$paths['extension'];

        return DB::transaction(function () use ($agent, $paths, $mediaId, $finalPath, $finalThumbPath): AgentWelcomeCard {
            $setting = Setting::query()->with('welcomeCard')->firstOrNew(['agent_id' => $agent->id]);
            $previousCard = $setting->welcomeCard;
            $previousPaths = $previousCard === null ? [] : array_values(array_filter([
                $previousCard->path,
                $previousCard->thumb_path,
            ]));

            if (Storage::disk('public')->exists($finalPath)) {
                Storage::disk('public')->delete($finalPath);
            }
            if (Storage::disk('public')->exists($finalThumbPath)) {
                Storage::disk('public')->delete($finalThumbPath);
            }

            Storage::disk('public')->move($paths['path'], $finalPath);
            Storage::disk('public')->move($paths['thumb_path'], $finalThumbPath);

            $card = AgentWelcomeCard::query()->updateOrCreate(
                ['agent_id' => $agent->id],
                [
                    'path' => $finalPath,
                    'thumb_path' => $finalThumbPath,
                    'media_id' => $mediaId,
                ]
            );

            $setting->friend_request_card_id = $card->id;
            $setting->save();

            foreach ($previousPaths as $oldPath) {
                if ($oldPath === $finalPath || $oldPath === $finalThumbPath) {
                    continue;
                }

                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            Log::channel('vk-images')->info('Визитка закреплена в постоянных файлах', [
                'agent_id' => $agent->id,
                'card_id' => $card->id,
                'path' => $finalPath,
                'thumb_path' => $finalThumbPath,
                'media_id' => $mediaId,
            ]);

            return $card->fresh() ?? $card;
        });
    }

    /**
     * @param  array{path?: string, thumb_path?: string}|null  $paths
     */
    public function cleanupLocalFiles(?array $paths): void
    {
        if ($paths === null) {
            return;
        }

        foreach (['path', 'thumb_path'] as $key) {
            $path = (string) ($paths[$key] ?? '');
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function deleteForSetting(Setting $setting): void
    {
        $setting->loadMissing('welcomeCard');
        $card = $setting->welcomeCard;

        $setting->friend_request_card_id = null;
        $setting->save();

        if ($card === null) {
            return;
        }

        $this->cleanupLocalFiles([
            'path' => $card->path,
            'thumb_path' => $card->thumb_path,
        ]);
        $card->delete();
    }

    private function createThumb(string $absoluteOriginal, string $absoluteThumb): void
    {
        $manager = ImageManager::usingDriver(GdDriver::class);
        $image = $manager->decodePath($absoluteOriginal);
        $image->scaleDown(width: self::THUMB_MAX_WIDTH);
        $image->save($absoluteThumb);
    }

    private function normalizeExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
