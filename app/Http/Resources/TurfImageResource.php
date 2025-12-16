<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TurfImageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_url' => url('storage/' . $this->image_path),
            'is_primary' => $this->is_primary,
            'order' => $this->order,
        ];
    }
}
