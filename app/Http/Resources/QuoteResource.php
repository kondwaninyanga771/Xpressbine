<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quote */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_request_id' => $this->job_request_id,
            'provider_profile_id' => $this->provider_profile_id,
            'amount' => $this->amount,
            'currency' => config('xpressbine.currency'),
            'message' => $this->message,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status?->value ?? $this->status,
            'provider' => new ProviderProfileResource($this->whenLoaded('provider')),
        ];
    }
}
