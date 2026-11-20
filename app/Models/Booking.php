<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'customer_id',
        'provider_profile_id',
        'category_id',
        'package_id',
        'job_request_id',
        'quote_id',
        'business_site_id',
        'status',
        'scheduled_at',
        'duration_minutes',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'customer_confirmed_at',
        'auto_release_at',
        'auto_released_at',
        'escrow_released_at',
        'amount',
        'commission_amount',
        'payout_amount',
        'address',
        'area',
        'latitude',
        'longitude',
        'notes',
        'client_absent',
        'entry_pin',
        'on_site_contact_name',
        'on_site_contact_phone',
        'fix_attempted',
        'fix_due_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'scheduled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'auto_released_at' => 'datetime',
            'escrow_released_at' => 'datetime',
            'amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'client_absent' => 'boolean',
            'fix_attempted' => 'boolean',
            'fix_due_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function jobRequest(): BelongsTo
    {
        return $this->belongsTo(JobRequest::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(BusinessSite::class, 'business_site_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BookingPhoto::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function involves(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->customer_id === $user->id) {
            return true;
        }

        return $user->isProvider()
            && $user->providerProfile
            && $this->provider_profile_id === $user->providerProfile->id;
    }
}
