<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'business_id',
        'auto_topup_enabled',
        'auto_topup_threshold',
        'auto_topup_amount',
    ];

    protected function casts(): array
    {
        return [
            'auto_topup_enabled' => 'boolean',
            'auto_topup_threshold' => 'decimal:2',
            'auto_topup_amount' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
