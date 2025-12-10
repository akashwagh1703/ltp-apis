<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'rule_text',
        'display_order',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
