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
        'ordered_qty',
        'received_qty',
        'unit',
        'line_number',
        'remarks',
    ];

    protected $casts = [
        'ordered_qty' => 'integer',
        'received_qty' => 'integer',
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
    public function getPendingQtyAttribute(): int
    {
        return max(0, $this->ordered_qty - $this->received_qty);
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
        $pending = $this->pending_qty;
        if ($this->received_qty === 0) {
            return 'Pending';
        }
        if ($pending === 0) {
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
