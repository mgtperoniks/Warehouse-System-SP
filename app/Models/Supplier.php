<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];

    public function itemVariants(): BelongsToMany
    {
        return $this->belongsToMany(ItemVariant::class, 'item_supplier')
                    ->withPivot(['supplier_sku', 'lead_time_days', 'price'])
                    ->withTimestamps();
    }
}
