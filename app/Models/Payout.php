<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'period_start',
        'period_end',
        'total_bookings',
        'total_amount',
        'commission_percentage',
        'commission_amount',
        'settlement_amount',
        'status',
        'paid_date',
        'transaction_id',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'paid_date' => 'date',
    ];

    protected $appends = ['payout_number'];

    public function getPayoutNumberAttribute()
    {
        return 'PO' . date('Ymd') . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }



    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function transactions()
    {
        return $this->hasMany(PayoutTransaction::class);
    }
}
