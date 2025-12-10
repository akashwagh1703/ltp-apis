<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_id',
        'booking_id',
        'booking_amount',
        'commission_rate',
        'commission_amount',
        'owner_amount',
    ];

    protected $casts = [
        'booking_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'owner_amount' => 'decimal:2',
    ];

    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
