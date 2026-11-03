<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferImageUpload extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function image(): BelongsTo
    {
        return $this->belongsTo(OfferImage::class, 'offer_image_id');
    }

    public function getOriginalUrlAttribute(): string
    {
        return $this->image->original_url;
    }
}
