<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'date',
        'start_time',
        'end_time',
        'price',
        'status',
        'locked_until',
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
        'locked_until' => 'datetime',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function booking()
    {
        return $this->hasOne(Booking::class, 'slot_id');
    }
    
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }
}
