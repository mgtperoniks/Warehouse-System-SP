<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_no',
        'warehouse_id',
        'operator_id',
        'date',
        'status',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    /**
     * Scope query to active warehouse context.
     */
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

    /**
     * Generate unique sequential adjustment code.
     */
    public static function generateCode(int $warehouseId, int $operatorId, string $date): string
    {
        $warehouse = Warehouse::find($warehouseId);
        $whCode = $warehouse ? strtoupper($warehouse->code) : 'WH';
        
        $dateStr = date('Ymd', strtotime($date));
        $prefix = "IA-{$whCode}-{$dateStr}-";

        // Query the highest sequential code matching the prefix
        $lastAdjustment = self::where('adjustment_no', 'like', $prefix . '%')
            ->orderBy('adjustment_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastAdjustment) {
            $parts = explode('-', $lastAdjustment->adjustment_no);
            $lastSeq = (int) end($parts);
            $sequence = $lastSeq + 1;
        }

        return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Synchronize parent header status based on children items.
     */
    public static function synchronizeStatus(int $headerId): void
    {
        $header = self::findOrFail($headerId);
        $hasWaiting = $header->items()->where('status', 'WAITING')->exists();
        
        $targetStatus = $hasWaiting ? 'WAITING_APPROVAL' : 'COMPLETED';
        
        if ($header->status !== $targetStatus) {
            $header->update(['status' => $targetStatus]);
        }
    }
}
