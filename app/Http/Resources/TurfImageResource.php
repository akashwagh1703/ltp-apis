<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TurfImageResource extends JsonResource
{
    public function toArray($request)
    {
        // Use domain URL instead of IP
        $imageUrl = 'https://api.playltp.in/storage/' . $this->image_path;
        
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_url' => $imageUrl,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
        ];
    }
}
