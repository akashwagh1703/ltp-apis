<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    public const STATUS_AWAITING_ADMIN = 'awaiting_admin';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'owner_id',
        'plan_id',
        'amount',
        'status',
        'marked_paid_at',
        'confirmed_at',
        'confirmed_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'marked_paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function confirmedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }

    public function isAwaitingAdmin(): bool
    {
        return $this->status === self::STATUS_AWAITING_ADMIN;
    }
}
