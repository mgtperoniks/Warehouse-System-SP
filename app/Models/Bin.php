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
        'warehouse_id',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeForActiveWarehouse($query)
    {
        $strict = env('WMS_GOVERNANCE_STRICT_MODE', true);
        $activeWarehouseId = session()->get('active_warehouse_id');

        if ($activeWarehouseId) {
            return $query->where($this->getTable() . '.warehouse_id', $activeWarehouseId);
        }

        if ($strict) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
