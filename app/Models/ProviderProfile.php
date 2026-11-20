<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'verification_status',
        'nrc_number',
        'years_experience',
        'hourly_rate',
        'service_radius_km',
        'rating_average',
        'rating_count',
        'is_featured',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'hourly_rate' => 'decimal:2',
            'service_radius_km' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'is_featured' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_provider');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProviderPhoto::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }
}
