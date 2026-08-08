<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivingSession extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_READY_REVIEW = 'READY_REVIEW';
    public const STATUS_REVIEWED = 'REVIEWED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'warehouse_id',
        'outstanding_purchase_order_id',
        'status',
        'created_by',
        'reviewed_by',
        'started_at',
        'reviewed_at',
        'completed_at',
        'remarks',
        'pdf_path',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Warehouse relationship.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Outstanding Purchase Order relationship.
     */
    public function outstandingPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(OutstandingPurchaseOrder::class, 'outstanding_purchase_order_id');
    }

    /**
     * Items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceivingSessionItem::class, 'receiving_session_id');
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reviewer relationship.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Signatures relationship.
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(ReceivingSignature::class, 'receiving_session_id');
    }

    /**
     * Scope for active warehouse isolation.
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
     * Scopes for statuses.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeReadyReview($query)
    {
        return $query->where('status', self::STATUS_READY_REVIEW);
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', self::STATUS_REVIEWED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Progress indicators - Accessors
     */
    public function getTotalLinesAttribute(): int
    {
        return $this->items()->count();
    }

    public function getVerifiedLinesAttribute(): int
    {
        return $this->items()->where('verification_status', ReceivingSessionItem::STATUS_VERIFIED)->count();
    }

    public function getRemovedLinesAttribute(): int
    {
        return $this->items()->where('verification_status', ReceivingSessionItem::STATUS_REMOVED)->count();
    }

    public function getPendingLinesAttribute(): int
    {
        return $this->items()->where('verification_status', ReceivingSessionItem::STATUS_PENDING)->count();
    }

    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->totalLines;
        if ($total === 0) {
            return 0;
        }
        return round((($this->verifiedLines + $this->removedLines) / $total) * 100, 2);
    }

    /**
     * Progress indicators - Direct Methods
     */
    public function totalLines(): int
    {
        return $this->totalLines;
    }

    public function verifiedLines(): int
    {
        return $this->verifiedLines;
    }

    public function removedLines(): int
    {
        return $this->removedLines;
    }

    public function pendingLines(): int
    {
        return $this->pendingLines;
    }

    public function completionPercentage(): float
    {
        return $this->completionPercentage;
    }
}
