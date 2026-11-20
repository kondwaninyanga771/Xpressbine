<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'wallet_id' => $this->wallet_id,
            'type' => $this->type?->value ?? $this->type,
            'amount' => $this->amount,
            'escrow_delta' => $this->escrow_delta,
            'currency' => $this->currency,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
