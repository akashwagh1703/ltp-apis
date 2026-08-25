<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_booking_amount',
        'max_discount',
        'usage_limit',
        'used_count',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_booking_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'used_count' => 'integer',
        'usage_limit' => 'integer',
    ];

    protected $appends = ['valid_until'];

    public function getValidUntilAttribute()
    {
        return $this->valid_to;
    }

    public function setValidUntilAttribute($value): void
    {
        $this->attributes['valid_to'] = $value;
    }
}
