<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'status',
        'department_id',
        'user_id',
        'reference',
        'total_price',
    ];

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockTransactionItem::class);
    }

    /**
     * Generate sequential code: OUT-YYYY-MM-DD-XXXX
     */
    public static function generateCode(): string
    {
        $date = now()->format('Y-m-d');
        $prefix = 'OUT-' . $date . '-';
        
        $lastTransaction = self::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $sequence = 1;
        if ($lastTransaction) {
            $lastSequence = (int) substr($lastTransaction->code, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
