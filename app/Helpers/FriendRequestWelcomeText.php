<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\StandardTextKey;
use App\Models\Setting;
use App\Services\StandardTextService;

final class FriendRequestWelcomeText
{
    public static function resolveTemplate(
        ?Setting $setting,
        ?StandardTextService $standardTexts = null,
    ): ?string {
        $custom = trim((string) ($setting?->friend_request_welcome_text ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $service = $standardTexts ?? new StandardTextService;

        return $service->body(StandardTextKey::FriendRequestWelcome);
    }

    public static function photoAttachment(int $ownerId, int $mediaId): string
    {
        return 'photo'.$ownerId.'_'.$mediaId;
    }
}
