<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    public static function get($key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set($key, $value, $type = 'string')
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
        
        Cache::forget("setting_{$key}");
        return $setting;
    }

    public static function getCommissionRate()
    {
        return (float) self::get('platform_commission_rate', 5.00);
    }

    public static function getBookingHoldMinutes(): int
    {
        return (int) self::get('booking_hold_minutes', 15);
    }

    public static function getBookingConfirmGraceMinutes(): int
    {
        return (int) self::get('booking_confirm_grace_minutes', 120);
    }

    public static function getBookingAdvancePercent(): int
    {
        $value = (int) self::get('booking_advance_percent', 50);

        return max(0, min(90, $value));
    }

    public static function isDefaultOtpEnabled(): bool
    {
        return self::get('default_otp_enabled', 'false') === 'true';
    }

    public static function platformUpiId(): ?string
    {
        $value = self::get('platform_upi_id', '');

        return filled($value) ? $value : null;
    }

    public static function platformQrUrl(): ?string
    {
        $path = self::get('platform_qr_path', '');
        if (!filled($path)) {
            return null;
        }

        return app(\App\Services\MediaService::class)->url($path);
    }
}
