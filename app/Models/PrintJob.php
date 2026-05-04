<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintJob extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';
    const STATUS_CANCELLED  = 'cancelled';

    const MAX_RETRIES = 3;

    protected $fillable = [
        'job_uuid',
        'item_variant_id',
        'printer_id',
        'template_id',
        'barcode_value',
        'payload_json',
        'copies',
        'status',
        'printed_at',
        'failed_at',
        'retry_count',
        'operator_id',
        'error_message',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'printed_at'   => 'datetime',
        'failed_at'    => 'datetime',
        'copies'       => 'integer',
        'retry_count'  => 'integer',
    ];

    // ─── Boot ───────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PrintJob $model) {
            if (empty($model->job_uuid)) {
                $model->job_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // ─── Relations ──────────────────────────────────────────────────────────────

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'template_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PrintLog::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->retry_count < self::MAX_RETRIES;
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'     => self::STATUS_COMPLETED,
            'printed_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'failed_at'     => now(),
            'error_message' => $reason,
            'retry_count'   => $this->retry_count + 1,
        ]);
    }
}
