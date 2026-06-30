<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function basoDocument(): HasOne
    {
        return $this->hasOne(BasoDocument::class);
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
        $baseCode = "IA-{$whCode}-{$dateStr}";

        // If baseCode does not exist, use it directly
        if (!self::where('adjustment_no', $baseCode)->exists()) {
            return $baseCode;
        }

        // If it exists, find the next sequential suffix (e.g. -2, -3)
        $prefix = $baseCode . '-';
        $existingCodes = self::where('adjustment_no', 'like', $prefix . '%')
            ->pluck('adjustment_no')
            ->toArray();

        $maxSequence = 1;
        foreach ($existingCodes as $code) {
            $suffixStr = substr($code, strlen($prefix));
            if (is_numeric($suffixStr)) {
                $seq = (int) $suffixStr;
                if ($seq > $maxSequence) {
                    $maxSequence = $seq;
                }
            }
        }

        $sequence = $maxSequence + 1;
        return $prefix . $sequence;
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
