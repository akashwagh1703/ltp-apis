<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'token',
        'device_type',
    ];

    public function user()
    {
        if ($this->user_type === 'owner') {
            return $this->belongsTo(Owner::class, 'user_id');
        }
        return $this->belongsTo(Player::class, 'user_id');
    }
}
