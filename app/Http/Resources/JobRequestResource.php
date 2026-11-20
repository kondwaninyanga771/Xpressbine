<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\JobRequest */
class JobRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'address' => $this->address,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'preferred_at' => $this->preferred_at,
            'client_absent' => $this->client_absent,
            'on_site_contact_name' => $this->on_site_contact_name,
            'on_site_contact_phone' => $this->on_site_contact_phone,
            'category_id' => $this->category_id,
            'business_site_id' => $this->business_site_id,
            'customer_id' => $this->customer_id,
            'distance_km' => $this->when(isset($this->distance_km), fn () => number_format((float) $this->distance_km, 2, '.', '')),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'customer' => new UserResource($this->whenLoaded('customer')),
            'quotes' => QuoteResource::collection($this->whenLoaded('quotes')),
        ];
    }
}
