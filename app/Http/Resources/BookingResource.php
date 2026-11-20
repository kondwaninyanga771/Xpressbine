<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $participant = $viewer && $this->involves($viewer);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status?->value ?? $this->status,
            'amount' => $this->amount,
            'currency' => config('xpressbine.currency'),
            'commission_amount' => $this->commission_amount,
            'payout_amount' => $this->payout_amount,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'accepted_at' => $this->accepted_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'customer_confirmed_at' => $this->customer_confirmed_at,
            'auto_release_at' => $this->auto_release_at,
            'auto_released_at' => $this->auto_released_at,
            'escrow_released_at' => $this->escrow_released_at,
            'escrow_balance' => Money::of($this->escrow_balance ?? null),
            'address' => $this->address,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
            'client_absent' => $this->client_absent,
            'entry_pin' => $this->when($participant && $this->client_absent, $this->entry_pin),
            'on_site_contact_name' => $this->when($participant, $this->on_site_contact_name),
            'on_site_contact_phone' => $this->when($participant, $this->on_site_contact_phone),
            'fix_attempted' => $this->fix_attempted,
            'fix_due_at' => $this->fix_due_at,
            'customer_id' => $this->customer_id,
            'provider_profile_id' => $this->provider_profile_id,
            'category_id' => $this->category_id,
            'package_id' => $this->package_id,
            'job_request_id' => $this->job_request_id,
            'quote_id' => $this->quote_id,
            'business_site_id' => $this->business_site_id,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'provider' => new ProviderProfileResource($this->whenLoaded('provider')),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'package' => $this->whenLoaded('package'),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'kind' => $photo->kind?->value ?? $photo->kind,
                'url' => $photo->url,
            ])),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
