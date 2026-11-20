<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProviderProfile */
class ProviderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $owner = $viewer && $viewer->id === $this->user_id;
        $admin = $viewer?->isAdmin();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bio' => $this->bio,
            'verification_status' => $this->verification_status?->value ?? $this->verification_status,
            'years_experience' => $this->years_experience,
            'hourly_rate' => $this->hourly_rate,
            'service_radius_km' => $this->service_radius_km,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'is_featured' => $this->is_featured,
            'verified_at' => $this->verified_at,
            'rejection_reason' => $this->when($owner || $admin, $this->rejection_reason),
            'nrc_number' => $this->when($owner || $admin, $this->nrc_number),
            'distance_km' => $this->when(isset($this->distance_km), fn () => number_format((float) $this->distance_km, 2, '.', '')),
            'user' => new UserResource($this->whenLoaded('user')),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])),
            'packages' => $this->whenLoaded('packages', fn () => $this->packages->map(fn ($package) => [
                'id' => $package->id,
                'category_id' => $package->category_id,
                'title' => $package->title,
                'description' => $package->description,
                'price' => $package->price,
                'duration_minutes' => $package->duration_minutes,
                'is_active' => $package->is_active,
            ])),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'caption' => $photo->caption,
                'url' => $photo->url,
            ])),
        ];
    }
}
