<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'title',
        'message',
        'body',
        'data',
        'type',
        'target',
        'user_ids',
        'scheduled_at',
        'sent_by',
        'status',
        'sent_at',
        'delivery_stats',
        'read_at',
    ];

    protected $casts = [
        'user_ids' => 'array',
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'delivery_stats' => 'array',
    ];

    public function sentBy()
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }

    public function markAsRead(): void
    {
        if ($this->read_at) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function getBodyAttribute($value)
    {
        return $value ?: $this->attributes['message'] ?? null;
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['message'] = $value;
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
