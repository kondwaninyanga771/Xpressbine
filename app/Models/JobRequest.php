<?php

namespace App\Models;

use App\Enums\JobRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'category_id',
        'business_site_id',
        'title',
        'description',
        'address',
        'area',
        'latitude',
        'longitude',
        'budget_min',
        'budget_max',
        'preferred_at',
        'status',
        'client_absent',
        'on_site_contact_name',
        'on_site_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'preferred_at' => 'datetime',
            'status' => JobRequestStatus::class,
            'client_absent' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(BusinessSite::class, 'business_site_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
