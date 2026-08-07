<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutstandingPurchaseOrder extends Model
{
    use HasFactory;

    // Status integer constants
    public const STATUS_PENDING = 1;
    public const STATUS_PARTIAL = 2;
    public const STATUS_CLOSED = 3;

    // Readiness integer constants
    public const READINESS_READY = 1;
    public const READINESS_NEEDS_CATALOG = 2;

    protected $fillable = [
        'warehouse_id',
        'receiving_session_id',
        'supplier_id',
        'supplier_name_snapshot',
        'supplier_code_snapshot',
        'po_number',
        'document_reference',
        'po_date',
        'expected_date',
        'status',
        'is_archived',
        'source',
        'remarks',
        'imported_at',
    ];

    protected $casts = [
        'po_date' => 'date',
        'expected_date' => 'date',
        'is_archived' => 'boolean',
        'imported_at' => 'datetime',
        'status' => 'integer',
        'receiving_session_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->warehouse_id)) {
                $model->warehouse_id = session('active_warehouse_id');
            }
        });
    }

    /**
     * Get human-readable label for status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ((int)$this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_CLOSED => 'Closed',
            default => 'Unknown',
        };
    }

    /**
     * Get computed receiving readiness status constant.
     */
    public function getReceivingReadinessAttribute(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $hasUnmatched = $items->contains(function ($item) {
            return $item->item_variant_id === null;
        });
        return $hasUnmatched ? self::READINESS_NEEDS_CATALOG : self::READINESS_READY;
    }

    /**
     * Get computed receiving readiness human-readable label.
     */
    public function getReceivingReadinessLabelAttribute(): string
    {
        return $this->receiving_readiness === self::READINESS_READY ? 'READY' : 'NEEDS CATALOG';
    }

    /**
     * Get count of matched catalog items.
     */
    public function getCatalogMatchedCountAttribute(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->whereNotNull('item_variant_id')->count();
    }

    /**
     * Get count of missing catalog items.
     */
    public function getCatalogMissingCountAttribute(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->whereNull('item_variant_id')->count();
    }

    /**
     * Get count of total item lines.
     */
    public function getTotalLineCountAttribute(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->count();
    }

    /**
     * Placeholder relationship for REC-02.
     */
    public function receivingSession()
    {
        return null;
    }

    /**
     * Recalculate status based on line items.
     */
    public function recalculateStatus(): void
    {
        $items = $this->items()->get();
        if ($items->isEmpty()) {
            $this->status = self::STATUS_PENDING;
            return;
        }

        $allClosed = true;
        $allPending = true;

        foreach ($items as $item) {
            if ($item->pending_qty > 0) {
                $allClosed = false;
            }
            if ($item->received_qty > 0) {
                $allPending = false;
            }
        }

        if ($allPending) {
            $this->status = self::STATUS_PENDING;
        } elseif ($allClosed) {
            $this->status = self::STATUS_CLOSED;
        } else {
            $this->status = self::STATUS_PARTIAL;
        }
    }

    /**
     * Dynamically heals item variant mappings if the matching variant has since been created in the catalog.
     */
    public function healVariantMappings(): void
    {
        $unmatchedItems = $this->items()->whereNull('item_variant_id')->get();
        foreach ($unmatchedItems as $item) {
            $variant = ItemVariant::resolveVariant($item->erp_code, $item->item_name_snapshot);
            if ($variant) {
                $item->item_variant_id = $variant->id;
                $item->save(); // Save to trigger status updates & recalculations
            }
        }
    }

    /**
     * Line items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OutstandingPurchaseOrderItem::class);
    }

    /**
     * Warehouse relationship.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Supplier master relationship.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Active Warehouse Scope.
     */
    public function scopeForActiveWarehouse($query)
    {
        $activeWarehouseId = session()->get('active_warehouse_id');
        if ($activeWarehouseId) {
            return $query->where($this->getTable() . '.warehouse_id', $activeWarehouseId);
        }

        $strict = env('WMS_GOVERNANCE_STRICT_MODE', true);
        if ($strict) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
