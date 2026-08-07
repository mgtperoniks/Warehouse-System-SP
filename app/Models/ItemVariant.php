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

    /**
     * Resolves an ItemVariant based on the hierarchical matching logic.
     * Priority 1: Exact ERP Code (case-insensitive)
     * Priority 2: ERP Code after removing family prefix (e.g. 5.16.TB.8M0776 -> 16.TB.8M0776)
     * Priority 3: Exact Item Name (case-insensitive, trimmed)
     * Priority 4: null
     */
    public static function resolveVariant(?string $erpCode, ?string $itemName): ?self
    {
        if (empty($erpCode) && empty($itemName)) {
            return null;
        }

        // Priority 1: Exact ERP Code match (case-insensitive)
        if (!empty($erpCode)) {
            $variant = self::withoutGlobalScopes()->whereRaw('LOWER(erp_code) = ?', [strtolower(trim($erpCode))])->first();
            if ($variant) {
                return $variant;
            }
        }

        // Priority 2: ERP Code after removing family prefix (e.g. 5.16.TB.8M0776 -> 16.TB.8M0776)
        if (!empty($erpCode)) {
            $importParts = explode('.', trim($erpCode), 2);
            if (count($importParts) > 1) {
                $importRemainder = strtolower(trim($importParts[1]));
                // Loop active domain variants to match remainder case-insensitively
                $variants = self::withoutGlobalScopes()->get();
                foreach ($variants as $v) {
                    $dbCodeLower = strtolower(trim($v->erp_code));
                    if ($dbCodeLower === $importRemainder) {
                        return $v;
                    }

                    $vParts = explode('.', trim($v->erp_code), 2);
                    $vRemainder = strtolower(trim(count($vParts) > 1 ? $vParts[1] : $v->erp_code));
                    if ($vRemainder === $importRemainder) {
                        return $v;
                    }
                }
            }
        }

        // Priority 3: Exact Item Name (case-insensitive, trim whitespace)
        if (!empty($itemName)) {
            $trimmedName = strtolower(trim($itemName));
            $variant = self::withoutGlobalScopes()->whereHas('item', function ($q) use ($trimmedName) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$trimmedName]);
            })->first();
            if ($variant) {
                return $variant;
            }
        }

        // Priority 4: null
        return null;
    }

    public function scopeForActiveWarehouse($query)
    {
        return app(\App\Services\Inventory\WarehouseDomainService::class)->applyDomainFilter($query);
    }
}
