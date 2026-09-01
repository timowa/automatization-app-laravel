<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\City;
use App\Enums\Deal;
use App\Enums\Category;
use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'offers';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'offer_id',
        'code',
        'stage',
        'status',
        'price',
        'area',
        'kitchen_area',
        'living_area',
        'city',
        'location',
        'agent_id',
        'deal',
        'category',
        'rooms',
        'rooms_offered',
        'floor',
        'floors_total',
        'commission',
        'deposit',
    ];

    protected $casts = [
        'location' => 'array',
        'area' => 'float',
        'kitchen_area' => 'float',
        'living_area' => 'float',
        'price' => 'integer',
        'commission' => 'integer',
        'deposit' => 'integer',
        'rooms' => 'integer',
        'rooms_offered' => 'integer',
        'floor' => 'integer',
        'floors_total' => 'integer',
        'created_at' => 'datetime',
    ];

    public function city(): ?City
    {
        return City::tryFrom((int) $this->getAttribute('city'));
    }

    public function deal(): ?Deal
    {
        return Deal::tryFrom((int) $this->getAttribute('deal'));
    }

    public function category(): ?Category
    {
        return Category::tryFrom((int) $this->getAttribute('category'));
    }

    public function status(): OfferStatus
    {
        return OfferStatus::tryFrom((int) $this->getAttribute('status'));
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(OfferImage::class)->orderBy('sort_order');
    }

    public function vkWallPosts(): HasMany
    {
        return $this->hasMany(VkWallPost::class, 'offer_id', 'id');
    }

    public function publication(): HasOne
    {
        return $this->hasOne(Publication::class, 'offer_id', 'id');
    }

    public function getPrice(): int
    {
        $price = (int) $this->price;
        if ($this->deal() === Deal::SALE && $price < 1_000_000) {
            $price *= 100;
        }
        return $price;
    }

    public function getBasePrice(): int
    {
        return (int) $this->price;
    }

    public function getAddressFromLocation(): string
    {
        $location = $this->location ?? [];
        return sprintf('%s, %s', $location['city'] ?? '', $location['address'] ?? '');
    }
}
