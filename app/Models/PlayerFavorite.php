<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerFavorite extends Model
{
    protected $fillable = [
        'player_id',
        'turf_id',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
