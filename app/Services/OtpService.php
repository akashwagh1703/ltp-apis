<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\Setting;
use Carbon\Carbon;

class OtpService
{
    public function generate($phone, $purpose = 'login')
    {
        $defaultOtpEnabled = Setting::get('default_otp_enabled', 'true') === 'true';
        $defaultOtp = Setting::get('default_otp', '999999');
        $expiryMinutes = (int) Setting::get('otp_expiry_minutes', 10);

        // Use default OTP if enabled, otherwise generate random
        $otp = $defaultOtpEnabled ? $defaultOtp : str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::create([
            'phone' => $phone,
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
        ]);

        return $otp;
    }

    public function verify($phone, $otp, $purpose = 'login')
    {
        $defaultOtpEnabled = Setting::get('default_otp_enabled', 'true') === 'true';
        $defaultOtp = Setting::get('default_otp', '999999');

        // Always accept default OTP if enabled
        if ($defaultOtpEnabled && $otp === $defaultOtp) {
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
            return true;
        }

        return false;
    }
}
