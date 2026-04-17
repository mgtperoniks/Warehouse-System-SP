<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'sku',
        'erp_code', // Primary Identity
        'brand',
        'unit',
        'price',
        'description',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function primaryBarcode(): BelongsTo
    {
        return $this->belongsTo(ItemBarcode::class)->where('is_primary', true);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'item_supplier')
                    ->withPivot(['supplier_sku', 'lead_time_days', 'price'])
                    ->withTimestamps();
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class);
    }
}
