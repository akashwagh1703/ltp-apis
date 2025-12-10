<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
        ];
    }
}
