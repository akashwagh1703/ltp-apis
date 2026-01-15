<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request)
    {
        // Determine payment type based on amounts
        $paymentType = 'full';
        if (isset($this->pending_amount) && $this->pending_amount > 0) {
            if (isset($this->paid_amount) && $this->paid_amount > 0) {
                $paymentType = 'partial';
            } else {
                $paymentType = 'pay_on_location';
            }
        }

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'slot_duration' => $this->slot_duration ?? 60,
            'amount' => (float) $this->amount,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'final_amount' => (float) ($this->final_amount ?? $this->amount),
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'pending_amount' => (float) ($this->pending_amount ?? 0),
            'advance_percentage' => $this->advance_percentage ? (float) $this->advance_percentage : null,
            'payment_type' => $paymentType,
            'booking_type' => $this->booking_type,
            'payment_mode' => $this->payment_mode ?? 'online',
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
