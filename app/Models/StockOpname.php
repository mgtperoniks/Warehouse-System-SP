<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'scope_type',
        'scope_id',
        'status',
        'created_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
