<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInItem extends Model
{
    use HasFactory;

    public const ERP_NOT_STARTED = 'NOT_STARTED';
    public const ERP_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'stock_in_receipt_id',
        'item_variant_id',
        'qty',
        'bin_id',
        'supplier_id',
        'erp_transfer_status',
        'transferred_by',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    /**
     * Parent receipt relationship.
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockInReceipt::class, 'stock_in_receipt_id');
    }

    /**
     * Item variant relationship.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    /**
     * Destination storage bin relationship.
     */
    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    /**
     * Supplier relationship.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
