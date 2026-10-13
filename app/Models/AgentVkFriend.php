<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vk\Deactivated;
use App\Enums\Vk\OccupationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentVkFriend extends Model
{
    protected $table = 'agents_vk_friends';

    protected $fillable = [
        'agent_id',
        'user_id',
        'bdate',
        'first_name',
        'last_name',
        'middle_name',
        'nickname',
        'sex',
        'domain',
        'city_id',
        'city_name',
        'country_id',
        'country_name',
        'mobile_phone',
        'home_phone',
        'university_id',
        'university_name',
        'faculty_id',
        'faculty_name',
        'graduation',
        'has_mobile',
        'timezone',
        'last_seen_at',
        'last_seen_platform',
        'online',
        'photo_50',
        'photo_100',
        'photo_200_orig',
        'photo_id',
        'relation',
        'status',
        'universities',
        'deactivated',
        'is_closed',
        'has_photo',
        'home_town',
        'occupation_type',
        'occupation_name',
        'education_form',
        'education_status',
        'blacklisted_by_me',
        'followers_count',
        'verified',
        'maiden_name',
        'relation_partner_id',
        'relation_partner_name',
        'schools',
        'career',
        'friend_list_names',
        'site',
        'last_birthday_wish_at',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'user_id' => 'integer',
        'sex' => 'integer',
        'city_id' => 'integer',
        'country_id' => 'integer',
        'university_id' => 'integer',
        'faculty_id' => 'integer',
        'graduation' => 'integer',
        'has_mobile' => 'boolean',
        'timezone' => 'integer',
        'last_seen_at' => 'integer',
        'last_seen_platform' => 'integer',
        'online' => 'boolean',
        'relation' => 'integer',
        'universities' => 'array',
        'is_closed' => 'boolean',
        'has_photo' => 'boolean',
        'blacklisted_by_me' => 'boolean',
        'followers_count' => 'integer',
        'verified' => 'boolean',
        'relation_partner_id' => 'integer',
        'schools' => 'array',
        'career' => 'array',
        'last_birthday_wish_at' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function profileUrl(): string
    {
        $domain = $this->domain;
        if (is_string($domain) && preg_match('/^[A-Za-z0-9_.]+$/', $domain) === 1) {
            return 'https://vk.com/'.$domain;
        }

        return 'https://vk.com/id'.$this->user_id;
    }

    public function safePhotoUrl(string $attribute): ?string
    {
        $url = $this->getAttribute($attribute);
        if (! is_string($url) || preg_match('#^https://#', $url) !== 1) {
            return null;
        }

        return $url;
    }

    public function bdateForDisplay(): string
    {
        $bdate = $this->bdate;
        if (! is_string($bdate) || $bdate === '') {
            return '—';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $bdate, $matches) === 1) {
            return $matches[3].'.'.$matches[2].'.'.$matches[1];
        }

        if (preg_match('/^(\d{2})-(\d{2})$/', $bdate, $matches) === 1) {
            return $matches[2].'.'.$matches[1];
        }

        return $bdate;
    }

    public function nicknameForDisplay(): string
    {
        $nickname = trim((string) ($this->nickname ?? ''));
        if ($nickname !== '') {
            return $nickname;
        }

        return $this->textOrDash($this->middle_name);
    }

    public function lastSeenForDisplay(): string
    {
        if ($this->last_seen_at === null || (int) $this->last_seen_at <= 0) {
            return '—';
        }

        return date('d.m.Y H:i', (int) $this->last_seen_at);
    }

    public function timezoneForDisplay(): string
    {
        if ($this->timezone === null) {
            return '—';
        }

        $hours = (int) $this->timezone;
        $sign = $hours > 0 ? '+' : '';

        return 'UTC'.$sign.$hours;
    }

    public function yesNoLabel(string $attribute): string
    {
        $value = $this->getAttribute($attribute);
        if ($value === null) {
            return '—';
        }

        return $value ? 'Да' : 'Нет';
    }

    public function textOrDash(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '—';
        }

        $text = trim((string) $value);

        return $text === '' ? '—' : $text;
    }

    public function pageStatusLabel(): string
    {
        return Deactivated::labelFor(is_string($this->deactivated) ? $this->deactivated : null);
    }

    public function profileVisibilityLabel(): string
    {
        if ($this->is_closed === null) {
            return '—';
        }

        return $this->is_closed ? 'Закрыт' : 'Открыт';
    }

    public function occupationForDisplay(): string
    {
        $type = OccupationType::labelFor(is_string($this->occupation_type) ? $this->occupation_type : null);
        $name = trim((string) ($this->occupation_name ?? ''));

        if ($type === null && $name === '') {
            return '—';
        }

        if ($type === null) {
            return $name;
        }

        return $name === '' ? $type : $type.': '.$name;
    }

    public function schoolsForDisplay(): string
    {
        return $this->namedItemsForDisplay($this->schools, 'year_graduated');
    }

    public function careerForDisplay(): string
    {
        $items = $this->listItems($this->career);
        if ($items === []) {
            return '—';
        }

        $parts = [];
        foreach ($items as $item) {
            $title = trim((string) ($item['position'] ?? $item['company'] ?? $item['name'] ?? ''));
            $from = is_numeric($item['from'] ?? null) ? (string) (int) $item['from'] : null;
            $until = is_numeric($item['until'] ?? null) ? (string) (int) $item['until'] : null;
            $years = $from !== null ? $from.($until !== null ? '–'.$until : '') : null;
            if ($title === '' && $years === null) {
                continue;
            }
            $parts[] = $years === null || $title === '' ? ($title !== '' ? $title : $years) : $title.' ('.$years.')';
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    public function universitiesForDisplay(): string
    {
        $items = $this->universities;
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($items) || $items === []) {
            return '—';
        }

        $parts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $faculty = trim((string) ($item['faculty_name'] ?? ''));
            $year = $item['graduation'] ?? null;
            $details = array_filter([
                $faculty !== '' ? $faculty : null,
                is_numeric($year) ? (string) (int) $year : null,
            ]);
            $parts[] = $details === [] ? $name : $name.' ('.implode(', ', $details).')';
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    private function namedItemsForDisplay(mixed $items, string $yearKey): string
    {
        $rows = $this->listItems($items);
        if ($rows === []) {
            return '—';
        }

        $parts = [];
        foreach ($rows as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $year = $item[$yearKey] ?? null;
            $parts[] = is_numeric($year) ? $name.' ('.(int) $year.')' : $name;
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listItems(mixed $items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, is_array(...)));
    }
}
