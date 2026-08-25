<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Owner extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'profile_image',
        'pan_number',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'status',
        'commission_rate',
        'fcm_token',
        'upi_id',
        'upi_qr_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'upi_qr_path',
    ];

    protected $appends = [
        'has_upi',
        'qr_url',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'commission_rate' => 'decimal:2',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function turfs()
    {
        return $this->hasMany(Turf::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    public function getCommissionRate()
    {
        // Use owner-specific rate if set, otherwise use platform default
        return $this->commission_rate ?? Setting::getCommissionRate();
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public static function normalizeUpiId(?string $upi): ?string
    {
        if ($upi === null) {
            return null;
        }

        $upi = strtolower(trim(preg_replace('/\s+/', '', $upi) ?? ''));

        return $upi === '' ? null : $upi;
    }

    public static function isValidUpiId(?string $upi): bool
    {
        $upi = self::normalizeUpiId($upi);

        return $upi !== null && (bool) preg_match('/^[a-z0-9._-]{2,256}@[a-z0-9][a-z0-9.-]{1,63}$/', $upi);
    }

    public function hasUpiSetup(): bool
    {
        return filled($this->upi_id) && filled($this->upi_qr_path);
    }

    public function getHasUpiAttribute(): bool
    {
        return $this->hasUpiSetup();
    }

    public function upiQrUrl(): ?string
    {
        if (!filled($this->upi_qr_path)) {
            return null;
        }

        return app(\App\Services\MediaService::class)->url($this->upi_qr_path);
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->upiQrUrl();
    }

    public function scopeWithUpiQr($query)
    {
        return $query->whereNotNull('upi_id')
            ->where('upi_id', '!=', '')
            ->whereNotNull('upi_qr_path')
            ->where('upi_qr_path', '!=', '');
    }

    public function currentSubscription(): ?Subscription
    {
        if ($this->relationLoaded('subscriptions')) {
            return $this->subscriptions
                ->sortByDesc(fn (Subscription $sub) => optional($sub->end_date)->timestamp ?? 0)
                ->first();
        }

        return $this->subscriptions()->with('plan')->orderByDesc('end_date')->first();
    }

    public function canAcceptOnlineBookings(): bool
    {
        $sub = $this->currentSubscription();
        if (!$sub || !$sub->end_date) {
            return false;
        }

        return $sub->end_date->copy()->endOfDay()->gte(now());
    }

    public function isVisibleInPlayerSearch(): bool
    {
        $sub = $this->currentSubscription();
        if (!$sub || !$sub->end_date) {
            return false;
        }

        return $sub->end_date->copy()
            ->addDays(Setting::getSubscriptionOverdueHideDays())
            ->endOfDay()
            ->gte(now());
    }

    public function scopeVisibleInPlayerSearch($query)
    {
        $cutoff = now()->subDays(Setting::getSubscriptionOverdueHideDays())->toDateString();

        return $query->whereHas('subscriptions', function ($q) use ($cutoff) {
            $q->whereDate('end_date', '>=', $cutoff);
        });
    }

    public function applySubscriptionPayment(SubscriptionPayment $payment): Subscription
    {
        $plan = $payment->plan ?? $payment->load('plan')->plan;
        if (!$plan) {
            throw new \RuntimeException('Subscription payment is missing a plan.');
        }

        $current = $this->currentSubscription();
        $stillValid = $current && $current->end_date && $current->end_date->copy()->endOfDay()->gte(now());

        if ($current && $stillValid) {
            $current->update([
                'plan_id' => $plan->id,
                'end_date' => $current->end_date->copy()->addDays($plan->duration_days),
                'status' => 'active',
                'amount_paid' => $payment->amount,
                'payment_method' => 'upi',
                'transaction_id' => 'fee-' . $payment->id,
            ]);

            return $current->fresh(['plan']);
        }

        if ($current) {
            $current->update(['status' => 'expired']);
        }

        return Subscription::create([
            'owner_id' => $this->id,
            'plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($plan->duration_days),
            'status' => 'active',
            'amount_paid' => $payment->amount,
            'payment_method' => 'upi',
            'transaction_id' => 'fee-' . $payment->id,
        ])->load('plan');
    }

    public function storeUpiQr($file): string
    {
        $media = app(\App\Services\MediaService::class);
        $media->delete($this->upi_qr_path);

        return $media->putUploadedFile($file, $media->ownerQrStem($this->id), false);
    }
}
