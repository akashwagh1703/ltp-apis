<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfAmenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'amenity_name',
        'icon',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
