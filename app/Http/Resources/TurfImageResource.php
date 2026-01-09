<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TurfImageResource extends JsonResource
{
    public function toArray($request)
    {
        $baseUrl = config('app.url');
        $imageUrl = $baseUrl . '/storage/' . $this->image_path;
        
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_url' => $imageUrl,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
        ];
    }
}
