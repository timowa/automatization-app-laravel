<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'agents';

    public $timestamps = false;

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = ['name', 'phone'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function vkUser(): HasOne
    {
        return $this->hasOne(VkUser::class, 'agent_id', 'id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'agent_id', 'id');
    }
}
