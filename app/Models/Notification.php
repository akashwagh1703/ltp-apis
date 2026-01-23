<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'target',
        'user_ids',
        'user_type',
        'scheduled_at',
        'sent_by',
        'status',
        'sent_at',
        'delivery_stats'
    ];

    protected $casts = [
        'user_ids' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivery_stats' => 'array'
    ];

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now());
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}