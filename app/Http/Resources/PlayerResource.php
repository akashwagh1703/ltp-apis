<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $this->profile_image,
            'status' => $this->status,
            'bookings_count' => $this->whenCounted('bookings'),
            'total_spent' => $this->total_spent ?? 0,
            'last_booking_date' => $this->last_booking_date,
            'created_at' => $this->created_at,
        ];
    }
}
