<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'payout_number' => $this->payout_number,
            'period_start' => $this->period_start->format('Y-m-d'),
            'period_end' => $this->period_end->format('Y-m-d'),
            'total_bookings' => $this->total_bookings,
            'total_revenue' => $this->total_revenue,
            'commission_amount' => $this->commission_amount,
            'payout_amount' => $this->payout_amount,
            'status' => $this->status,
            'processed_at' => $this->processed_at,
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'transactions' => PayoutTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
