<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'plan_id' => $this->plan_id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'marked_paid_at' => $this->marked_paid_at,
            'confirmed_at' => $this->confirmed_at,
            'confirmed_by' => $this->confirmed_by,
            'note' => $this->note,
            'owner' => $this->whenLoaded('owner', function () {
                return [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                    'phone' => $this->owner->phone,
                ];
            }),
            'plan' => $this->whenLoaded('plan', function () {
                return [
                    'id' => $this->plan->id,
                    'name' => $this->plan->name,
                    'type' => $this->plan->type,
                    'price' => (float) $this->plan->price,
                    'duration_days' => $this->plan->duration_days,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
