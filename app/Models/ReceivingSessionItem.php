<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingSessionItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REMOVED = 'REMOVED';

    protected $fillable = [
        'receiving_session_id',
        'outstanding_purchase_order_item_id',
        'item_variant_id',
        'expected_qty',
        'received_qty',
        'verification_status',
        'removed_reason',
        'remarks',
    ];

    protected $casts = [
        'expected_qty' => 'integer',
        'received_qty' => 'integer',
    ];

    /**
     * Parent receiving session relationship.
     */
    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class, 'receiving_session_id');
    }

    /**
     * Original Outstanding PO line item relationship.
     */
    public function outstandingPurchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(OutstandingPurchaseOrderItem::class, 'outstanding_purchase_order_item_id');
    }

    /**
     * Item variant relationship.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    /**
     * Helper methods to query verification states.
     */
    public function isPending(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function isRemoved(): bool
    {
        return $this->verification_status === self::STATUS_REMOVED;
    }
}
