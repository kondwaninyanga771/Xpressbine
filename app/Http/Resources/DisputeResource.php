<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Dispute */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'raised_by' => $this->raised_by,
            'reason' => $this->reason,
            'evidence_url' => $this->evidence_url,
            'status' => $this->status?->value ?? $this->status,
            'outcome' => $this->outcome,
            'refund_amount' => $this->refund_amount,
            'provider_response' => $this->provider_response,
            'provider_responded_at' => $this->provider_responded_at,
            'admin_notes' => $this->admin_notes,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'booking' => new BookingResource($this->whenLoaded('booking')),
        ];
    }
}
