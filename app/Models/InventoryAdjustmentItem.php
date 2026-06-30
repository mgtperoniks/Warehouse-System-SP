<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_adjustment_id',
        'bin_id',
        'item_variant_id',
        'system_qty',
        'physical_qty',
        'variance',
        'reason_code',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        
        // Snapshots
        'item_name_snapshot',
        'erp_code_snapshot',
        'bin_code_snapshot',
        'unit_snapshot',
        'warehouse_name_snapshot',
        'operator_name_snapshot',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get age/duration elapsed since submission.
     */
    public function getAgeAttribute(): string
    {
        return Carbon::parse($this->created_at)->diffForHumans(null, true);
    }
}
