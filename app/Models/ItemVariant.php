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
        'last_opname_at',
        'procurement_type',
        'inventory_class',
        'lead_time_days',
    ];

    protected $attributes = [
        'procurement_type' => 'LOCAL',
        'inventory_class' => 'CONSUMABLE',
        'lead_time_days' => 30,
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ActiveWarehouseDomainScope);
    }

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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_variant_id');
    }

    public function scopeForActiveWarehouse($query)
    {
        return app(\App\Services\Inventory\WarehouseDomainService::class)->applyDomainFilter($query);
    }
}
