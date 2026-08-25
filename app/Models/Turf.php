<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turf extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'sport_type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'size',
        'capacity',
        'opening_time',
        'closing_time',
        'slot_duration',
        'pricing_type',
        'uniform_price',
        'status',
        'is_featured',
        'rejection_reason',
        'submitted_at',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'pending';
    public const STATUS_LIVE = 'approved';
    public const STATUS_SUSPENDED = 'suspended';

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_featured' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function images()
    {
        return $this->hasMany(TurfImage::class);
    }

    public function amenities()
    {
        return $this->hasMany(TurfAmenity::class);
    }

    public function rules()
    {
        return $this->hasMany(TurfRule::class);
    }

    public function pricing()
    {
        return $this->hasMany(TurfPricing::class);
    }

    public function slots()
    {
        return $this->hasMany(TurfSlot::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(PlayerFavorite::class);
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function updateRequests()
    {
        return $this->hasMany(TurfUpdateRequest::class);
    }
}
