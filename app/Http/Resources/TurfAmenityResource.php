<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TurfAmenityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amenity_name' => $this->amenity_name,
        ];
    }
}
