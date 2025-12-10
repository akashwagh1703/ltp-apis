<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'payout_number',
        'period_start',
        'period_end',
        'total_bookings',
        'total_revenue',
        'commission_amount',
        'payout_amount',
        'status',
        'processed_at',
        'remarks',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function transactions()
    {
        return $this->hasMany(PayoutTransaction::class);
    }
}
