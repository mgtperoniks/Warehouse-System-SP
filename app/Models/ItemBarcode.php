<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBarcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_variant_id',
        'barcode',
        'type',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    /**
     * Boot the model.
     * Ensure only one is_primary exists per variant.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->is_primary) {
                // Set all other barcodes of this variant to NOT primary
                static::where('item_variant_id', $model->item_variant_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
