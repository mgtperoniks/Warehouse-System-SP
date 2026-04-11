<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalBarcodeCounter extends Model
{
    protected $fillable = [
        'prefix',
        'current_value',
    ];
}
