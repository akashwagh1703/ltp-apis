<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'owner_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'amount_paid',
        'payment_method',
        'transaction_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount_paid' => 'decimal:2',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function updateStatus()
    {
        $now = Carbon::now();
        $daysUntilExpiry = $now->diffInDays($this->end_date, false);

        if ($daysUntilExpiry < 0) {
            $this->status = 'expired';
        } elseif ($daysUntilExpiry <= 7) {
            $this->status = 'expiring_soon';
        } else {
            $this->status = 'active';
        }

        $this->save();
    }
}
