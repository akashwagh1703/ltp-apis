<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'image_path',
        'is_primary',
        'order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TurfImage $image) {
            if (filled($image->image_path)) {
                app(\App\Services\MediaService::class)->delete($image->image_path);
            }
        });
    }

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return app(\App\Services\MediaService::class)->url($this->image_path);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
