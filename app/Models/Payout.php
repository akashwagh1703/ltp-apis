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

    protected static function booted()
    {
        static::created(function ($payout) {
            if (!$payout->payout_number) {
                $payout->payout_number = 'PO' . date('Ymd') . str_pad($payout->id, 4, '0', STR_PAD_LEFT);
                $payout->save();
            }
        });
    }

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'paid_date' => 'date',
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
