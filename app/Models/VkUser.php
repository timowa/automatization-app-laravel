<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vk\FriendStatus;
use App\Enums\Vk\LastSeenPlatform;
use App\Enums\Vk\Relation;
use App\Enums\Vk\Sex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VkUser extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'vk_users';

    public $timestamps = false;

    protected $hidden = ['vk_token'];

    protected $fillable = [
        'agent_id',
        'vk_user_id',
        'vk_token',
        'email',
        'is_token_available',
        'first_name',
        'last_name',
        'screen_name',
        'domain',
        'deactivated',
        'is_closed',
        'can_access_closed',
        'sex',
        'bdate',
        'relation',
        'home_town',
        'city_id',
        'city_name',
        'country_id',
        'country_name',
        'online',
        'last_seen_at',
        'last_seen_platform',
        'followers_count',
        'friend_status',
        'status',
        'verified',
        'raw',
    ];

    protected $casts = [
        'is_token_available' => 'boolean',
        'is_closed' => 'boolean',
        'can_access_closed' => 'boolean',
        'online' => 'boolean',
        'verified' => 'boolean',
        'raw' => 'array',
        'sex' => 'integer',
        'relation' => 'integer',
        'last_seen_platform' => 'integer',
        'friend_status' => 'integer',
        'followers_count' => 'integer',
        'city_id' => 'integer',
        'country_id' => 'integer',
        'last_seen_at' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function normalizeBdate(?string $bdate): ?string
    {
        if ($bdate === null || $bdate === '') {
            return null;
        }

        $parts = explode('.', $bdate);
        $count = count($parts);

        if ($count === 3) {
            [$day, $month, $year] = $parts;
            return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        }

        if ($count === 2) {
            [$day, $month] = $parts;
            return sprintf('%02d-%02d', (int) $month, (int) $day);
        }

        return $bdate;
    }

    public function getToken(): string
    {
        return $this->vk_token ?? '';
    }

    public function getAgentId(): int
    {
        return (int) $this->agent_id;
    }
}
