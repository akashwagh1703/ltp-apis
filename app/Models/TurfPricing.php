<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfPricing extends Model
{
    use HasFactory;

    protected $table = 'turf_pricing';

    protected $fillable = [
        'turf_id',
        'day_type',
        'time_slot',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
