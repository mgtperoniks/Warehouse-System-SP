<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_variant_id',
        'bin_id',
        'supplier_id',
        'type',
        'qty',
        'reference',
        'created_by',
        'warehouse_id',
        'operator_id',
        'terminal_id',
        'terminal_session_id',
        'linked_transaction_id',
    ];

    protected static function booted()
    {
        static::updating(function ($mov) {
            throw new \Exception("Operational Audit Violation: Updates on stock_movements are immutable.");
        });

        static::deleting(function ($mov) {
            throw new \Exception("Operational Audit Violation: Deleting movements is forbidden.");
        });
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'linked_transaction_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'reference', 'code');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockInReceipt::class, 'reference', 'receipt_code');
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
