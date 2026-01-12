<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'player_id',
        'turf_id',
        'slot_id',
        'owner_id',
        'booking_date',
        'start_time',
        'end_time',
        'slot_duration',
        'amount',
        'discount_amount',
        'final_amount',
        'paid_amount',
        'pending_amount',
        'advance_percentage',
        'platform_commission',
        'owner_payout',
        'commission_rate',
        'payment_mode',
        'payment_status',
        'booking_type',
        'booking_status',
        'player_name',
        'player_phone',
        'player_email',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'advance_percentage' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'owner_payout' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slot()
    {
        return $this->belongsTo(TurfSlot::class, 'slot_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function payoutTransaction()
    {
        return $this->hasOne(PayoutTransaction::class);
    }
}
