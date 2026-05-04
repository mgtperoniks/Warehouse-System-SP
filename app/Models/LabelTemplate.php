<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_code',
        'template_name',
        'template_type',
        'printer_language',
        'width_mm',
        'height_mm',
        'dpi',
        'template_body',
        'version',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'dpi' => 'integer',
        'version' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
