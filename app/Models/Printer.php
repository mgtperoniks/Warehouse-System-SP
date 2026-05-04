<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'printer_code',
        'printer_name',
        'communication_type',
        'printer_language',
        'printer_ip',
        'printer_port',
        'dpi',
        'supports_direct_thermal',
        'supports_thermal_transfer',
        'max_label_width_mm',
        'location',
        'status',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_direct_thermal' => 'boolean',
        'supports_thermal_transfer' => 'boolean',
        'last_seen_at' => 'datetime',
        'printer_port' => 'integer',
        'dpi' => 'integer',
        'max_label_width_mm' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }
}
