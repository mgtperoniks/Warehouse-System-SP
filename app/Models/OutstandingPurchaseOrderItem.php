<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutstandingPurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'outstanding_purchase_order_id',
        'item_variant_id',
        'erp_code',
        'item_name_snapshot',
        'department_name',
        'ordered_qty',
        'received_qty',
        'erp_ordered_qty',
        'erp_received_qty',
        'erp_outstanding_qty',
        'erp_sync_status',
        'erp_snapshot_at',
        'unit',
        'line_number',
        'remarks',
    ];

    protected $casts = [
        'ordered_qty' => 'float',
        'received_qty' => 'float',
        'erp_ordered_qty' => 'float',
        'erp_received_qty' => 'float',
        'erp_outstanding_qty' => 'float',
        'erp_snapshot_at' => 'datetime',
        'line_number' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(function ($item) {
            $po = $item->outstandingPurchaseOrder;
            if ($po) {
                $po->recalculateStatus();
                $po->save();
            }
        });

        static::deleted(function ($item) {
            $po = $item->outstandingPurchaseOrder;
            if ($po) {
                $po->recalculateStatus();
                $po->save();
            }
        });
    }

    /**
     * Get calculated pending quantity.
     * Pending Qty = Ordered Qty - Received Qty (Never negative).
     */
    public function getPendingQtyAttribute(): float
    {
        return max(0.0, (float)$this->ordered_qty - (float)$this->received_qty);
    }

    /**
     * Check if ERP is lagging behind WMS receiving state.
     */
    public function isErpBehind(): bool
    {
        return $this->erp_sync_status === 'ERP_BEHIND' || ((float)$this->received_qty > (float)$this->erp_received_qty);
    }

    /**
     * Get catalog status (Matched or Needs Catalog).
     */
    public function getCatalogStatusAttribute(): string
    {
        return $this->item_variant_id !== null ? 'Matched' : 'Needs Catalog';
    }

    /**
     * Helper to verify if catalog is matched.
     */
    public function isCatalogMatched(): bool
    {
        return $this->item_variant_id !== null;
    }

    /**
     * Get human-readable status for line item.
     */
    public function getStatusAttribute(): string
    {
        $pending = (float)$this->pending_qty;
        $received = (float)$this->received_qty;

        if ($received <= 0.0001) {
            return 'Pending';
        }
        if ($pending <= 0.0001) {
            return 'Closed';
        }
        return 'Partial';
    }

    /**
     * Parent order relationship.
     */
    public function outstandingPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(OutstandingPurchaseOrder::class);
    }

    /**
     * Item variant master relationship.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
