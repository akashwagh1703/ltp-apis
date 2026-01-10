<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $phoneNumberId;
    protected $accessToken;
    protected $enabled;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->enabled = config('services.notification.whatsapp_enabled', false);
    }

    /**
     * Send template message via WhatsApp Cloud API
     */
    public function sendTemplateMessage($phone, $templateName, $parameters = [])
    {
        if (!$this->enabled) {
            Log::info('WhatsApp disabled via config, skipping message');
            return false;
        }

        if (!$this->isConfigured()) {
            Log::warning('WhatsApp not configured, skipping message');
            return false;
        }

        try {
            $phone = $this->formatPhoneNumber($phone);
            
            $components = [];
            if (!empty($parameters)) {
                $bodyParams = [];
                foreach ($parameters as $param) {
                    $bodyParams[] = [
                        'type' => 'text',
                        'text' => (string) $param
                    ];
                }
                
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParams
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                    'components' => $components
                ]
            ];

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                Log::info("WhatsApp sent successfully", [
                    'phone' => $phone,
                    'template' => $templateName
                ]);
                return true;
            }

            Log::error("WhatsApp API error", [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP via WhatsApp
     */
    public function sendOtp($phone, $otp, $userType = 'player')
    {
        $templateName = $userType === 'owner' ? 'owner_login_otp' : 'player_login_otp';
        return $this->sendTemplateMessage($phone, $templateName, [$otp]);
    }

    /**
     * Send turf creation details to owner
     */
    public function sendTurfDetails($phone, $turfData)
    {
        $params = [
            $turfData['name'],
            $turfData['city'] . ', ' . $turfData['state'],
            $turfData['sport_type'],
            $turfData['status'],
            $turfData['owner_name'],
            $turfData['owner_phone']
        ];
        
        return $this->sendTemplateMessage($phone, 'turf_created_notification', $params);
    }

    /**
     * Send booking confirmation
     */
    public function sendBookingConfirmation($phone, $bookingData, $isOnline = true)
    {
        $templateName = $isOnline ? 'booking_confirmed_online' : 'booking_confirmed_offline';
        
        $params = [
            $bookingData['booking_number'],
            $bookingData['turf_name'],
            $bookingData['booking_date'],
            $bookingData['start_time'],
            $bookingData['end_time'],
            $bookingData['final_amount'],
        ];
        
        if (!$isOnline) {
            $params[] = $bookingData['payment_mode'];
        }
        
        return $this->sendTemplateMessage($phone, $templateName, $params);
    }

    /**
     * Send cancellation notification to player
     */
    public function sendCancellationToPlayer($phone, $bookingData, $cancelledBy = 'player')
    {
        $templateName = $cancelledBy === 'player' 
            ? 'booking_cancelled_player' 
            : 'booking_cancelled_owner_to_player';
        
        $params = [
            $bookingData['booking_number'],
            $bookingData['turf_name'],
            $bookingData['booking_date'],
            $bookingData['start_time'],
            $bookingData['cancellation_reason'] ?? 'No reason provided',
        ];
        
        return $this->sendTemplateMessage($phone, $templateName, $params);
    }

    /**
     * Send cancellation notification to owner
     */
    public function sendCancellationToOwner($phone, $bookingData)
    {
        $params = [
            $bookingData['booking_number'],
            $bookingData['player_name'],
            $bookingData['booking_date'],
            $bookingData['start_time'],
        ];
        
        return $this->sendTemplateMessage($phone, 'booking_cancelled_notification_owner', $params);
    }

    /**
     * Send booking completed notification
     */
    public function sendBookingCompleted($phone, $bookingData)
    {
        $params = [
            $bookingData['booking_number'],
            $bookingData['turf_name'],
            $bookingData['booking_date'],
            $bookingData['start_time'],
        ];
        
        return $this->sendTemplateMessage($phone, 'booking_completed', $params);
    }

    /**
     * Format phone number to international format
     */
    protected function formatPhoneNumber($phone)
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming India +91)
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        
        return $phone;
    }

    /**
     * Check if WhatsApp is configured
     */
    protected function isConfigured()
    {
        return !empty($this->apiUrl) 
            && !empty($this->phoneNumberId) 
            && !empty($this->accessToken);
    }
}
