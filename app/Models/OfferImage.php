<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferImage extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'offer_images';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'offer_id',
        'original_url',
        'media_id',
        'sort_order',
    ];

    protected $casts = [
        'media_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}