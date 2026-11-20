<?php

namespace App\Models;

use App\Enums\PhotoKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BookingPhoto extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'kind',
        'path',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'kind' => PhotoKind::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
