<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    public const SEND_LIMIT = 3;
    public const SEND_WINDOW_MINUTES = 10;
    public const FAIL_LIMIT = 5;
    public const FAIL_LOCK_MINUTES = 30;

    public function generate($phone, $purpose = 'login')
    {
        $this->assertNotLocked($phone, $purpose);
        $this->assertSendLimit($phone, $purpose);

        $defaultOtpEnabled = Setting::isDefaultOtpEnabled();
        $defaultOtp = Setting::get('default_otp', '999999');
        $expiryMinutes = (int) Setting::get('otp_expiry_minutes', 10);

        $otp = $defaultOtpEnabled ? $defaultOtp : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::create([
            'phone' => $phone,
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
        ]);

        $sendKey = $this->sendKey($phone, $purpose);
        Cache::add($sendKey, 0, now()->addMinutes(self::SEND_WINDOW_MINUTES));
        Cache::increment($sendKey);

        return $otp;
    }

    public function verify($phone, $otp, $purpose = 'login')
    {
        $this->assertNotLocked($phone, $purpose);

        $defaultOtpEnabled = Setting::isDefaultOtpEnabled();
        $defaultOtp = Setting::get('default_otp', '999999');

        if ($defaultOtpEnabled && $otp === $defaultOtp) {
            $this->clearFailures($phone, $purpose);
            return true;
        }

        $record = Otp::where('phone', $phone)
            ->where('otp', $otp)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($record) {
            $record->update(['verified_at' => Carbon::now()]);
            $this->clearFailures($phone, $purpose);
            return true;
        }

        $this->recordFailure($phone, $purpose);

        return false;
    }

    protected function assertSendLimit(string $phone, string $purpose): void
    {
        $count = (int) Cache::get($this->sendKey($phone, $purpose), 0);
        if ($count >= self::SEND_LIMIT) {
            throw new \RuntimeException('Too many OTP requests. Please try after 10 minutes.', 429);
        }
    }

    protected function assertNotLocked(string $phone, string $purpose): void
    {
        if (Cache::has($this->lockKey($phone, $purpose))) {
            throw new \RuntimeException('Too many failed attempts. Try again in 30 minutes.', 429);
        }
    }

    protected function recordFailure(string $phone, string $purpose): void
    {
        $failKey = $this->failKey($phone, $purpose);
        Cache::add($failKey, 0, now()->addMinutes(self::FAIL_LOCK_MINUTES));
        $fails = (int) Cache::increment($failKey);

        if ($fails >= self::FAIL_LIMIT) {
            Cache::put($this->lockKey($phone, $purpose), true, now()->addMinutes(self::FAIL_LOCK_MINUTES));
            throw new \RuntimeException('Too many failed attempts. Try again in 30 minutes.', 429);
        }
    }

    protected function clearFailures(string $phone, string $purpose): void
    {
        Cache::forget($this->failKey($phone, $purpose));
        Cache::forget($this->lockKey($phone, $purpose));
    }

    protected function sendKey(string $phone, string $purpose): string
    {
        return "otp:send:{$purpose}:{$phone}";
    }

    protected function failKey(string $phone, string $purpose): string
    {
        return "otp:fail:{$purpose}:{$phone}";
    }

    protected function lockKey(string $phone, string $purpose): string
    {
        return "otp:lock:{$purpose}:{$phone}";
    }
}
