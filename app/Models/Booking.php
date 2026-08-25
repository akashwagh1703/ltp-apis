<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'player_id',
        'turf_id',
        'slot_id',
        'owner_id',
        'booking_date',
        'start_time',
        'end_time',
        'slot_duration',
        'amount',
        'coupon_id',
        'discount_amount',
        'final_amount',
        'paid_amount',
        'pending_amount',
        'advance_percentage',
        'platform_commission',
        'owner_payout',
        'commission_rate',
        'payment_mode',
        'payment_status',
        'booking_type',
        'booking_status',
        'player_name',
        'player_phone',
        'player_email',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'marked_paid_at',
        'payment_hold_expires_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'advance_percentage' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'owner_payout' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'marked_paid_at' => 'datetime',
        'payment_hold_expires_at' => 'datetime',
    ];

    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATUS_PAY_ON_ARRIVAL = 'pay_on_arrival';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const PAY_MODE_UPI = 'upi';
    public const PAY_MODE_CASH = 'cash';
    public const PAY_MODE_PAY_ON_ARRIVAL = 'pay_on_arrival';
    public const PAY_MODE_PAY_ON_TURF = 'pay_on_turf';

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slot()
    {
        return $this->belongsTo(TurfSlot::class, 'slot_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function payoutTransaction()
    {
        return $this->hasOne(PayoutTransaction::class);
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->booking_status === self::STATUS_AWAITING_CONFIRMATION;
    }

    public function isPayOnArrival(): bool
    {
        return $this->booking_status === self::STATUS_PAY_ON_ARRIVAL
            || $this->payment_mode === self::PAY_MODE_PAY_ON_ARRIVAL
            || $this->payment_mode === self::PAY_MODE_PAY_ON_TURF;
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function needsOwnerPaymentConfirm(): bool
    {
        return in_array($this->booking_status, [
            self::STATUS_AWAITING_CONFIRMATION,
            self::STATUS_PAY_ON_ARRIVAL,
        ], true);
    }

    public function paymentInstructions(): array
    {
        $this->loadMissing('owner', 'turf');
        $owner = $this->owner;

        $payMethod = $this->booking_status === self::STATUS_PAY_ON_ARRIVAL
            || $this->payment_mode === self::PAY_MODE_PAY_ON_ARRIVAL
            ? 'pay_on_arrival'
            : 'upi';

        $total = (float) ($this->final_amount ?? $this->amount);
        $dueNow = $this->dueNowAmount();

        return [
            'amount' => $dueNow,
            'total_amount' => $total,
            'remaining_amount' => round(max(0, $total - $dueNow), 2),
            'advance_percentage' => $this->advance_percentage ? (float) $this->advance_percentage : null,
            'upi_id' => $owner?->upi_id,
            'qr_url' => $owner?->upiQrUrl(),
            'owner_name' => $owner?->name,
            'turf_name' => $this->turf?->name,
            'pay_method' => $this->isAdvancePay() ? 'upi_advance' : $payMethod,
        ];
    }

    public function isAdvancePay(): bool
    {
        return $this->advance_percentage !== null && (float) $this->advance_percentage > 0
            && (float) $this->advance_percentage < 100;
    }

    public function dueNowAmount(): float
    {
        $total = (float) ($this->final_amount ?? $this->amount);
        if (!$this->isAdvancePay()) {
            return $total;
        }

        return round($total * ((float) $this->advance_percentage) / 100, 2);
    }

    public function releaseHeldSlots(): int
    {
        return TurfSlot::where('turf_id', $this->turf_id)
            ->whereDate('date', $this->booking_date)
            ->where(function ($q) {
                $q->whereBetween('start_time', [$this->start_time, $this->end_time])
                    ->orWhereBetween('end_time', [$this->start_time, $this->end_time]);
            })
            ->whereIn('status', ['booked_online', 'booked_offline'])
            ->update(['status' => 'available']);
    }

    public function expireAndRelease(): void
    {
        if (!$this->needsOwnerPaymentConfirm()) {
            return;
        }

        $this->update(['booking_status' => self::STATUS_EXPIRED]);
        $this->releaseHeldSlots();
    }

    public static function expireUnconfirmed(): int
    {
        $expired = 0;

        \DB::transaction(function () use (&$expired) {
            static::query()
                ->whereIn('booking_status', [
                    self::STATUS_AWAITING_CONFIRMATION,
                    self::STATUS_PAY_ON_ARRIVAL,
                ])
                ->whereNotNull('payment_hold_expires_at')
                ->where('payment_hold_expires_at', '<=', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (self $booking) use (&$expired) {
                    $booking->expireAndRelease();
                    $expired++;
                });
        });

        return $expired;
    }

    public static function holdExpiresAt(string $payMethod, $bookingDate, $startTime): Carbon
    {
        if ($payMethod === 'pay_on_arrival') {
            $date = $bookingDate instanceof Carbon
                ? $bookingDate->format('Y-m-d')
                : (string) $bookingDate;

            return Carbon::parse(trim($date . ' ' . $startTime))->addMinutes(30);
        }

        return now()->addMinutes(Setting::getBookingHoldMinutes());
    }
}
