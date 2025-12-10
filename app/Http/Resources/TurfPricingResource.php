<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TurfPricingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'day_type' => $this->day_type,
            'time_slot' => $this->time_slot,
            'price' => $this->price,
        ];
    }
}
