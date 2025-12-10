<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'image_url',
        'is_primary',
        'display_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
