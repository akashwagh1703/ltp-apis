<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'amount' => (float) $this->amount,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'final_amount' => (float) ($this->final_amount ?? $this->amount),
            'booking_type' => $this->booking_type,
            'status' => $this->booking_status,
            'payment_status' => $this->payment_status,
            'player_name' => $this->player_name,
            'player_phone' => $this->player_phone,
            'player_email' => $this->player_email,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at,
            'turf' => new TurfResource($this->whenLoaded('turf')),
            'player' => new PlayerResource($this->whenLoaded('player')),
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'payment' => $this->whenLoaded('payment', function() {
                return $this->payment ? new PaymentResource($this->payment) : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
