<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model
{
    // Audit logs are immutable — no updated_at
    const UPDATED_AT = null;

    // Action type constants
    const ACTION_PRINT   = 'PRINT';
    const ACTION_REPRINT = 'REPRINT';
    const ACTION_CANCEL  = 'CANCEL';
    const ACTION_FAILED  = 'FAILED';

    protected $fillable = [
        'print_job_id',
        'item_variant_id',
        'action_type',
        'action_reason',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────────

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Get badge color based on action type (for UI).
     */
    public function getBadgeColorAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_PRINT   => 'green',
            self::ACTION_REPRINT => 'blue',
            self::ACTION_CANCEL  => 'orange',
            self::ACTION_FAILED  => 'red',
            default              => 'gray',
        };
    }
}
