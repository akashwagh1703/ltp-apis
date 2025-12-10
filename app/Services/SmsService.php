<?php

namespace App\Services;

class SmsService
{
    public function send($phone, $message)
    {
        $gateway = config('services.sms.gateway');

        if ($gateway === 'msg91') {
            return $this->sendViaMSG91($phone, $message);
        } elseif ($gateway === 'twilio') {
            return $this->sendViaTwilio($phone, $message);
        }

        return false;
    }

    private function sendViaMSG91($phone, $message)
    {
        // MSG91 API implementation
        return true; // Placeholder
    }

    private function sendViaTwilio($phone, $message)
    {
        // Twilio API implementation
        return true; // Placeholder
    }

    public function sendOtp($phone, $otp)
    {
        // SMS not integrated yet - OTP is static 999999
        \Log::info("OTP for {$phone}: {$otp}");
        return true;
    }

    public function sendBookingConfirmation($phone, $bookingNumber, $turfName, $date, $time)
    {
        $message = "Booking confirmed! #{$bookingNumber} at {$turfName} on {$date} at {$time}.";
        return $this->send($phone, $message);
    }
}
