<?php

namespace App\Services;

use App\Models\Otp;
use Carbon\Carbon;

class OtpService
{
    public function generate($phone, $purpose = 'login')
    {
        // Static OTP for development (no SMS integration yet)
        $otp = '999999';
        $expiryMinutes = config('app.otp_expiry_minutes', 10);

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
        // For development, always accept 999999
        if ($otp === '999999') {
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
