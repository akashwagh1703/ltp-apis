<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TurfResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'description' => $this->description,
            'sport_type' => $this->sport_type,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'size' => $this->size,
            'capacity' => $this->capacity,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'slot_duration' => $this->slot_duration,
            'pricing_type' => $this->pricing_type,
            'uniform_price' => $this->uniform_price,
            'weekend_price' => $this->whenLoaded('pricing', function () {
                $row = $this->pricing->firstWhere('day_type', 'weekend');
                return $row ? (float) $row->price : null;
            }),
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at,
            'distance' => isset($this->distance) ? round((float) $this->distance, 1) : null,
            'is_favorite' => (bool) ($this->is_favorite ?? false),
            'average_rating' => $this->approved_reviews_avg_rating !== null ? round((float) $this->approved_reviews_avg_rating, 1) : null,
            'reviews_count' => (int) ($this->approved_reviews_count ?? 0),
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'owner_has_upi' => $this->whenLoaded('owner', fn () => (bool) $this->owner?->hasUpiSetup()),
            'images' => TurfImageResource::collection($this->whenLoaded('images')),
            'amenities' => TurfAmenityResource::collection($this->whenLoaded('amenities')),
            'pricing' => TurfPricingResource::collection($this->whenLoaded('pricing')),
            'created_at' => $this->created_at,
        ];
    }
}
