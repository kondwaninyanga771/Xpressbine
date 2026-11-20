<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateRecord extends Model
{
    protected $table = 'estimates';

    protected $fillable = [
        'user_id',
        'description',
        'trade',
        'trade_label',
        'urgency',
        'price_min',
        'price_max',
        'currency',
        'confidence',
        'rationale',
        'driver',
        'photo_provided',
    ];

    protected function casts(): array
    {
        return [
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'confidence' => 'decimal:2',
            'photo_provided' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
