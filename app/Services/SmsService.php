<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $enabled;
    protected $gateway;

    public function __construct()
    {
        $this->enabled = config('services.notification.sms_enabled', false);
        $this->gateway = config('services.sms.gateway', 'msg91');
    }

    public function send($phone, $message)
    {
        if (!$this->enabled) {
            Log::info('SMS disabled via config, skipping message');
            return false;
        }

        if ($this->gateway === 'msg91') {
            return $this->sendViaMSG91($phone, $message);
        } elseif ($this->gateway === 'twilio') {
            return $this->sendViaTwilio($phone, $message);
        }

        return false;
    }

    private function sendViaMSG91($phone, $message)
    {
        $authKey = config('services.sms.msg91.auth_key');
        $senderId = config('services.sms.msg91.sender_id');

        if (empty($authKey) || empty($senderId)) {
            Log::warning('MSG91 not configured, skipping SMS');
            return false;
        }

        try {
            $phone = $this->formatPhoneNumber($phone);

            $response = Http::timeout(10)->get('https://api.msg91.com/api/sendhttp.php', [
                'authkey' => $authKey,
                'mobiles' => $phone,
                'message' => $message,
                'sender' => $senderId,
                'route' => '4', // Transactional route
                'country' => '91',
            ]);

            if ($response->successful()) {
                Log::info('MSG91 SMS sent successfully', ['phone' => $phone]);
                return true;
            }

            Log::error('MSG91 SMS failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('MSG91 exception: ' . $e->getMessage());
            return false;
        }
    }

    private function sendViaTwilio($phone, $message)
    {
        // Twilio API implementation placeholder
        Log::info('Twilio not implemented yet');
        return false;
    }

    public function sendOtp($phone, $otp)
    {
        if (!$this->enabled) {
            Log::info('SMS disabled, OTP logged only: ' . $otp);
            return true;
        }

        if ($this->gateway === 'msg91') {
            return $this->sendOtpViaMSG91($phone, $otp);
        }

        // Fallback to simple SMS
        $message = "Your LTP OTP is {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->send($phone, $message);
    }

    private function sendOtpViaMSG91($phone, $otp)
    {
        $authKey = config('services.sms.msg91.auth_key');
        $templateId = config('services.sms.msg91.otp_template_id');

        if (empty($authKey)) {
            Log::warning('MSG91 not configured, OTP logged only: ' . $otp);
            return false;
        }

        try {
            $phone = $this->formatPhoneNumber($phone);

            // Use MSG91 OTP API if template ID is configured
            if (!empty($templateId)) {
                $response = Http::timeout(10)
                    ->withHeaders(['authkey' => $authKey])
                    ->post('https://control.msg91.com/api/v5/otp', [
                        'template_id' => $templateId,
                        'mobile' => $phone,
                        'otp' => $otp,
                    ]);
            } else {
                // Fallback to simple SMS
                $message = "Your LTP OTP is {$otp}. Valid for 10 minutes. Do not share this code.";
                return $this->sendViaMSG91($phone, $message);
            }

            if ($response->successful()) {
                Log::info('MSG91 OTP sent successfully', ['phone' => $phone]);
                return true;
            }

            Log::error('MSG91 OTP failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('MSG91 OTP exception: ' . $e->getMessage());
            return false;
        }
    }

    public function sendBookingConfirmation($phone, $bookingNumber, $turfName, $date, $time)
    {
        if (!$this->enabled) {
            Log::info('SMS disabled, skipping booking confirmation');
            return false;
        }

        $message = "Booking confirmed! #{$bookingNumber} at {$turfName} on {$date} at {$time}.";
        return $this->send($phone, $message);
    }

    protected function formatPhoneNumber($phone)
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove country code if present
        if (strlen($phone) > 10 && substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        }
        
        return $phone;
    }
}
