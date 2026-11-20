<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $self = $viewer && $viewer->id === $this->id;
        $admin = $viewer?->isAdmin();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role instanceof UserRole ? $this->role->value : $this->role,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'email' => $this->when($self || $admin, $this->email),
            'phone' => $this->when($self || $admin, $this->phone),
            'mobile_money_provider' => $this->when($self || $admin, $this->mobile_money_provider),
            'mobile_money_number' => $this->when($self || $admin, $this->mobile_money_number),
            'strikes' => $this->when($self || $admin, $this->strikes),
            'suspended_until' => $this->when($self || $admin, $this->suspended_until),
            'banned_at' => $this->when($self || $admin, $this->banned_at),
            'ban_reason' => $this->when($self || $admin, $this->ban_reason),
            'flagged_at' => $this->when($admin, $this->flagged_at),
            'provider_profile_id' => $this->whenLoaded('providerProfile', fn () => $this->providerProfile?->id),
            'business_id' => $this->whenLoaded('business', fn () => $this->business?->id),
        ];
    }
}
