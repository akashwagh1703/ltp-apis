<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $this->profile_image,
            'pan_number' => $this->pan_number,
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'ifsc_code' => $this->ifsc_code,
            'status' => $this->status,
            'turfs_count' => $this->whenCounted('turfs'),
            'active_subscription' => $this->whenLoaded('activeSubscription'),
            'created_at' => $this->created_at,
        ];
    }
}
