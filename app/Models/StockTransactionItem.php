<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransactionItem extends Model
{
    use HasFactory;

    public const ERP_NOT_STARTED = 'NOT_STARTED';
    public const ERP_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'stock_transaction_id',
        'item_variant_id',
        'bin_id',
        'qty',
        'item_name_snapshot',
        'erp_code_snapshot',
        'unit_snapshot',
        'price_snapshot',
        'total_price_snapshot',
        'erp_transfer_status',
        'transferred_by',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function transferredBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
