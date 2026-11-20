<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Dispute extends Model
{
    protected $fillable = [
        'booking_id',
        'raised_by',
        'reason',
        'evidence_path',
        'status',
        'outcome',
        'refund_amount',
        'provider_response',
        'provider_responded_at',
        'admin_id',
        'admin_notes',
        'resolved_at',
    ];

    protected $appends = ['evidence_url'];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'refund_amount' => 'decimal:2',
            'provider_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        if (! $this->evidence_path) {
            return null;
        }

        return Storage::disk('public')->url($this->evidence_path);
    }
}
