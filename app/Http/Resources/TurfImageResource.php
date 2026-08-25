<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use Illuminate\Http\Resources\Json\JsonResource;

class TurfImageResource extends JsonResource
{
    public function toArray($request)
    {
        $media = app(MediaService::class);

        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_url' => $media->url($this->image_path),
            'thumb_url' => $media->thumbUrl($this->image_path),
            'is_primary' => $this->is_primary,
            'order' => $this->order,
        ];
    }
}
