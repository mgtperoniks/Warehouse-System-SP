<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bin extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'item_variant_id',
        'code',
        'current_qty',
        'min_qty',
        'lead_time_days',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }
}
