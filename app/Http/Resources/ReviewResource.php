<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'player' => new PlayerResource($this->whenLoaded('player')),
            'turf' => new TurfResource($this->whenLoaded('turf')),
            'created_at' => $this->created_at,
        ];
    }
}
