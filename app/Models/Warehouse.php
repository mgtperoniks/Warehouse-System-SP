<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public function bins()
    {
        return $this->hasMany(Bin::class);
    }

    public function userAccess()
    {
        return $this->hasMany(UserWarehouseAccess::class);
    }

    public function families()
    {
        return $this->hasMany(WarehouseFamilyAssignment::class);
    }

    public function allowedFamilyCodes(): array
    {
        return $this->families()->pluck('family_code')->toArray();
    }
}
