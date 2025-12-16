<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'payout_number' => $this->payout_number ?? 'PO' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'period_start' => $this->period_start->format('Y-m-d'),
            'period_end' => $this->period_end->format('Y-m-d'),
            'total_bookings' => $this->total_bookings,
            'total_revenue' => (float) ($this->total_amount ?? 0),
            'commission_percentage' => (float) ($this->commission_percentage ?? 5.00),
            'commission_amount' => (float) ($this->commission_amount ?? 0),
            'payout_amount' => (float) ($this->settlement_amount ?? 0),
            'status' => $this->status,
            'processed_at' => $this->processed_at,
            'paid_date' => $this->paid_date,
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'transactions' => PayoutTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
