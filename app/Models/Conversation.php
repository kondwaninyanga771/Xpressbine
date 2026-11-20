<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_low_id',
        'user_high_id',
        'booking_id',
    ];

    public function lowUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function highUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function involves(User $user): bool
    {
        return $this->user_low_id === $user->id || $this->user_high_id === $user->id;
    }

    public function otherUserId(User $user): int
    {
        return $this->user_low_id === $user->id ? $this->user_high_id : $this->user_low_id;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function pair(int $left, int $right): array
    {
        return $left < $right ? [$left, $right] : [$right, $left];
    }
}
