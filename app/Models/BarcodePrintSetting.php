<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodePrintSetting extends Model
{
    protected $fillable = [
        'default_printer_type',
        'default_printer_ip',
        'default_label_type',
        'default_copies',
        'updated_by'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the singleton settings record.
     * Ensures only one row exists (ID 1).
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'default_printer_type' => 'EPSON',
            'default_label_type'   => 'ITEM_LABEL',
            'default_copies'       => 1
        ]);
    }
}
