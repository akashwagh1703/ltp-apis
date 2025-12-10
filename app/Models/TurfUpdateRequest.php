<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfUpdateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'owner_id',
        'request_type',
        'changes',
        'status',
        'admin_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
}
