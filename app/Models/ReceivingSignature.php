<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'receiving_session_id',
        'role',
        'signature_path',
        'signed_by',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /**
     * Parent receiving session relationship.
     */
    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class, 'receiving_session_id');
    }

    /**
     * Signer user relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
