<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $enabled;
    protected $gateway;

    public function __construct()
    {
        $this->enabled = Setting::get('sms_enabled', 'false') === 'true';
        $this->gateway = 'msg91'; // Always use MSG91
    }

    public function send($phone, $message)
    {
        if (!$this->enabled) {
            Log::info('SMS disabled via admin settings, skipping message');
            return false;
        }

        return $this->sendViaMSG91($phone, $message);
    }

    private function sendViaMSG91($phone, $message)
    {
        $authKey = Setting::get('msg91_auth_key');
        $senderId = Setting::get('msg91_sender_id', 'LTPLAY');

        if (empty($authKey)) {
            Log::warning('MSG91 auth key not configured in admin settings');
            return false;
        }

        try {
            $phone = $this->formatPhoneNumber($phone);

            $response = Http::timeout(10)->get('https://api.msg91.com/api/sendhttp.php', [
                'authkey' => $authKey,
                'mobiles' => $phone,
                'message' => $message,
                'sender' => $senderId,
                'route' => '4',
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

    public function sendOtp($phone, $otp)
    {
        if (!$this->enabled) {
            Log::info('SMS disabled via admin settings, OTP logged only: ' . $otp);
            return true;
        }

        return $this->sendOtpViaMSG91($phone, $otp);
    }

    private function sendOtpViaMSG91($phone, $otp)
    {
        $authKey = Setting::get('msg91_auth_key');
        $templateId = Setting::get('msg91_otp_template_id');

        if (empty($authKey)) {
            Log::warning('MSG91 not configured in admin settings, OTP logged only: ' . $otp);
            return false;
        }

        try {
            $phone = $this->formatPhoneNumber($phone);

            if (!empty($templateId)) {
                $response = Http::timeout(10)
                    ->withHeaders(['authkey' => $authKey])
                    ->post('https://control.msg91.com/api/v5/otp', [
                        'template_id' => $templateId,
                        'mobile' => $phone,
                        'otp' => $otp,
                    ]);
            } else {
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
            Log::info('SMS disabled via admin settings, skipping booking confirmation');
            return false;
        }

        $message = "Booking confirmed! #{$bookingNumber} at {$turfName} on {$date} at {$time}.";
        return $this->send($phone, $message);
    }

    protected function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) > 10 && substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        }
        
        return $phone;
    }
}
