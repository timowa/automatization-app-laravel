<?php

declare(strict_types=1);

namespace App\Services\Vk;

use App\Models\VkUser;
use Illuminate\Support\Carbon;
use JsonException;

final class VkFriendRowMapper
{
    /**
     * @param  array<string, mixed>  $friend
     * @param  array<int, string>  $friendListNames
     * @return array<string, mixed>|null
     */
    public function map(int $agentId, array $friend, VkUser $vkUser, Carbon $now, array $friendListNames = []): ?array
    {
        $userId = $this->intOrNull($friend['id'] ?? null);
        if ($userId === null || $userId <= 0) {
            return null;
        }

        $lastSeen = is_array($friend['last_seen'] ?? null) ? $friend['last_seen'] : [];
        $nickname = $this->stringOrNull($friend['nickname'] ?? null, 255);
        $bdate = $friend['bdate'] ?? null;
        $occupation = is_array($friend['occupation'] ?? null) ? $friend['occupation'] : [];
        $partner = is_array($friend['relation_partner'] ?? null) ? $friend['relation_partner'] : [];
        $occupationType = is_string($occupation['type'] ?? null) ? $occupation['type'] : null;

        return [
            'agent_id' => $agentId,
            'user_id' => $userId,
            'bdate' => $vkUser->normalizeBdate(is_scalar($bdate) ? (string) $bdate : null),
            'first_name' => $this->stringOrNull($friend['first_name'] ?? null, 100) ?? '',
            'last_name' => $this->stringOrNull($friend['last_name'] ?? null, 100) ?? '',
            'middle_name' => $this->stringOrNull($friend['middle_name'] ?? $friend['nickname'] ?? null, 100),
            'nickname' => $nickname,
            'sex' => $this->tinyIntOrNull($friend['sex'] ?? null),
            'domain' => $this->stringOrNull($friend['domain'] ?? null, 255),
            'city_id' => $this->placeId($friend, 'city'),
            'city_name' => $this->placeTitle($friend, 'city'),
            'country_id' => $this->placeId($friend, 'country'),
            'country_name' => $this->placeTitle($friend, 'country'),
            'mobile_phone' => $this->contactValue($friend, 'mobile_phone'),
            'home_phone' => $this->contactValue($friend, 'home_phone'),
            'university_id' => $this->intOrNull($this->educationValue($friend, 'university')),
            'university_name' => $this->stringOrNull($this->educationValue($friend, 'university_name'), 255),
            'faculty_id' => $this->intOrNull($this->educationValue($friend, 'faculty')),
            'faculty_name' => $this->stringOrNull($this->educationValue($friend, 'faculty_name'), 255),
            'graduation' => $this->graduationOrNull($this->educationValue($friend, 'graduation')),
            'has_mobile' => $this->boolOrNull($friend, 'has_mobile'),
            'timezone' => $this->smallIntOrNull($friend['timezone'] ?? null),
            'last_seen_at' => $this->intOrNull($lastSeen['time'] ?? null),
            'last_seen_platform' => $this->tinyIntOrNull($lastSeen['platform'] ?? null),
            'online' => $this->boolOrNull($friend, 'online'),
            'photo_50' => $this->stringOrNull($friend['photo_50'] ?? null, 2048),
            'photo_100' => $this->stringOrNull($friend['photo_100'] ?? null, 2048),
            'photo_200_orig' => $this->stringOrNull($friend['photo_200_orig'] ?? null, 2048),
            'photo_id' => $this->stringOrNull($friend['photo_id'] ?? null, 64),
            'relation' => $this->tinyIntOrNull($friend['relation'] ?? null),
            'status' => $this->stringOrNull($friend['status'] ?? null, 2000),
            'universities' => $this->jsonList($friend['universities'] ?? null),
            'deactivated' => $this->deactivated($friend),
            'is_closed' => $this->boolOrNull($friend, 'is_closed'),
            'has_photo' => $this->boolOrNull($friend, 'has_photo'),
            'home_town' => $this->stringOrNull($friend['home_town'] ?? null, 255),
            'occupation_type' => in_array($occupationType, ['work', 'school', 'university'], true) ? $occupationType : null,
            'occupation_name' => $this->stringOrNull($occupation['name'] ?? null, 255),
            'education_form' => $this->stringOrNull($this->educationValue($friend, 'education_form'), 255),
            'education_status' => $this->stringOrNull($this->educationValue($friend, 'education_status'), 255),
            'blacklisted_by_me' => $this->boolOrNull($friend, 'blacklisted_by_me'),
            'followers_count' => $this->nonNegativeIntOrNull($friend['followers_count'] ?? null),
            'verified' => $this->boolOrNull($friend, 'verified'),
            'maiden_name' => $this->stringOrNull($friend['maiden_name'] ?? null, 100),
            'relation_partner_id' => $this->positiveIntOrNull($partner['id'] ?? null),
            'relation_partner_name' => $this->partnerName($partner),
            'schools' => $this->jsonList($friend['schools'] ?? null),
            'career' => $this->jsonList($friend['career'] ?? null),
            'friend_list_names' => $this->friendListNames($friend, $friendListNames),
            'site' => $this->stringOrNull($friend['site'] ?? null, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<string, mixed>  $friend
     */
    private function educationValue(array $friend, string $key): mixed
    {
        $education = $friend['education'] ?? null;
        if (is_array($education) && array_key_exists($key, $education)) {
            return $education[$key];
        }

        return $friend[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $friend
     */
    private function contactValue(array $friend, string $key): ?string
    {
        $contacts = $friend['contacts'] ?? null;
        $value = is_array($contacts) ? ($contacts[$key] ?? null) : null;
        if ($value === null || $value === '') {
            $value = $friend[$key] ?? null;
        }

        return $this->stringOrNull($value, 64);
    }

    /**
     * @param  array<string, mixed>  $friend
     */
    private function placeId(array $friend, string $key): ?int
    {
        $place = $friend[$key] ?? null;
        if (! is_array($place)) {
            return null;
        }

        return $this->intOrNull($place['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $friend
     */
    private function placeTitle(array $friend, string $key): ?string
    {
        $place = $friend[$key] ?? null;
        if (is_string($place)) {
            return $this->stringOrNull($place, 255);
        }

        if (! is_array($place)) {
            return null;
        }

        return $this->stringOrNull($place['title'] ?? null, 255);
    }

    private function jsonList(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $friend
     */
    private function deactivated(array $friend): ?string
    {
        $value = $friend['deactivated'] ?? null;
        if (! is_string($value) || ! in_array($value, ['deleted', 'banned'], true)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $partner
     */
    private function partnerName(array $partner): ?string
    {
        $name = trim(implode(' ', array_filter([
            $this->stringOrNull($partner['first_name'] ?? null, 100),
            $this->stringOrNull($partner['last_name'] ?? null, 100),
        ])));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $friend
     * @param  array<int, string>  $namesById
     */
    private function friendListNames(array $friend, array $namesById): ?string
    {
        $lists = $friend['lists'] ?? null;
        if (is_string($lists)) {
            $ids = preg_split('/\s*,\s*/', $lists) ?: [];
        } elseif (is_array($lists)) {
            $ids = $lists;
        } else {
            return null;
        }

        $labels = [];
        foreach ($ids as $id) {
            $listId = $this->positiveIntOrNull($id);
            if ($listId === null) {
                continue;
            }
            $labels[] = $namesById[$listId] ?? (string) $listId;
        }

        if ($labels === []) {
            return null;
        }

        return mb_substr(implode(', ', $labels), 0, 500);
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $int = $this->intOrNull($value);

        return $int !== null && $int > 0 ? $int : null;
    }

    private function nonNegativeIntOrNull(mixed $value): ?int
    {
        $int = $this->intOrNull($value);

        return $int !== null && $int >= 0 ? $int : null;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function boolOrNull(array $source, string $key): ?int
    {
        if (! array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }

        return filter_var($source[$key], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function graduationOrNull(mixed $value): ?int
    {
        $year = $this->intOrNull($value);
        if ($year === null || $year <= 0 || $year > 65535) {
            return null;
        }

        return $year;
    }

    private function tinyIntOrNull(mixed $value): ?int
    {
        $int = $this->intOrNull($value);
        if ($int === null || $int < 0 || $int > 255) {
            return null;
        }

        return $int;
    }

    private function smallIntOrNull(mixed $value): ?int
    {
        $int = $this->intOrNull($value);
        if ($int === null || $int < -32768 || $int > 32767) {
            return null;
        }

        return $int;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function stringOrNull(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }
}
