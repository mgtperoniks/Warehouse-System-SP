<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BasoDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_adjustment_id',
        'baso_number',
        'generated_by',
        'generated_at',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * Get the inventory adjustment session this document reports.
     */
    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    /**
     * Get the user who generated this document.
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Generate the unique sequential BASO document number.
     * Format: BASO-{WH_CODE}-{YYYYMMDD}-{SEQ}
     */
    public static function generateNumber(int $warehouseId, string $date): string
    {
        return DB::transaction(function () use ($warehouseId, $date) {
            $warehouse = Warehouse::find($warehouseId);
            $whCode = $warehouse ? strtoupper($warehouse->code) : 'WH';
            
            $dateStr = date('Ymd', strtotime($date));
            $prefix = "BASO-{$whCode}-{$dateStr}-";
            
            // Sequential numbering using lock for update to avoid race conditions
            $lastDoc = self::where('baso_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderBy('baso_number', 'desc')
                ->first();
                
            $sequence = 1;
            if ($lastDoc) {
                $parts = explode('-', $lastDoc->baso_number);
                $lastSeq = (int) end($parts);
                $sequence = $lastSeq + 1;
            }
            
            return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
        });
    }
}
