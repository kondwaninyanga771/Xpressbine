<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Rating */
class RatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'rater_id' => $this->rater_id,
            'ratee_id' => $this->ratee_id,
            'stars' => $this->stars,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'rater' => new UserResource($this->whenLoaded('rater')),
        ];
    }
}
