<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $credentialsPath;
    protected $projectId;

    public function __construct()
    {
        $this->credentialsPath = base_path(config('services.fcm.credentials_path'));
        $credentials = json_decode(file_get_contents($this->credentialsPath), true);
        $this->projectId = $credentials['project_id'];
    }

    public function sendToUser($userId, $userType, $title, $body, $data = [], $type = 'general')
    {
        $tokens = FcmToken::where('user_id', $userId)
            ->where('user_type', $userType)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No FCM tokens found for user: {$userId} ({$userType})");
            return false;
        }

        // Store notification in database
        Notification::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'type' => $type,
        ]);

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToAll($userType, $title, $body, $data = [], $type = 'general')
    {
        $tokens = FcmToken::where('user_type', $userType)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No FCM tokens found for user type: {$userType}");
            return false;
        }

        // Store notification for all users
        $userIds = FcmToken::where('user_type', $userType)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'type' => $type,
            ]);
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    protected function sendToTokens($tokens, $title, $body, $data = [])
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('Failed to get FCM access token');
            return false;
        }

        $success = true;
        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => 'high',
                    ],
                ],
            ];

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

                if (!$response->successful()) {
                    Log::error('FCM notification failed', ['token' => $token, 'response' => $response->body()]);
                    $success = false;
                }
            } catch (\Exception $e) {
                Log::error('FCM notification exception: ' . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    protected function getAccessToken()
    {
        try {
            $credentials = json_decode(file_get_contents($this->credentialsPath), true);
            $now = time();
            $payload = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->createJWT($payload, $credentials['private_key']);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get access token: ' . $e->getMessage());
            return null;
        }
    }

    protected function createJWT($payload, $privateKey)
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $segments[] = $this->base64UrlEncode($signature);
        
        return implode('.', $segments);
    }

    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function sendBookingNotification($booking, $toOwner = false)
    {
        if ($toOwner) {
            return $this->sendToUser(
                $booking->owner_id,
                'owner',
                'New Booking Received',
                "Booking #{$booking->booking_number} for {$booking->turf->name}",
                [
                    'type' => 'booking',
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ],
                'booking'
            );
        }

        return $this->sendToUser(
            $booking->player_id,
            'player',
            'Booking Confirmed',
            "Your booking #{$booking->booking_number} at {$booking->turf->name} is confirmed",
            [
                'type' => 'booking',
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
            ],
            'booking'
        );
    }

    public function sendCancellationNotification($booking, $toOwner = false)
    {
        if ($toOwner) {
            return $this->sendToUser(
                $booking->owner_id,
                'owner',
                'Booking Cancelled',
                "Booking #{$booking->booking_number} has been cancelled",
                [
                    'type' => 'cancellation',
                    'booking_id' => $booking->id,
                ],
                'booking'
            );
        }

        return $this->sendToUser(
            $booking->player_id,
            'player',
            'Booking Cancelled',
            "Your booking #{$booking->booking_number} has been cancelled",
            [
                'type' => 'cancellation',
                'booking_id' => $booking->id,
            ],
            'booking'
        );
    }

    public function sendPaymentNotification($booking)
    {
        return $this->sendToUser(
            $booking->owner_id,
            'owner',
            'Payment Received',
            "Payment of ₹{$booking->paid_amount} received for booking #{$booking->booking_number}",
            [
                'type' => 'payment',
                'booking_id' => $booking->id,
                'amount' => $booking->paid_amount,
            ],
            'payment'
        );
    }

    public function sendReminderNotification($booking)
    {
        return $this->sendToUser(
            $booking->player_id,
            'player',
            'Booking Reminder',
            "Your booking at {$booking->turf->name} starts in 2 hours",
            [
                'type' => 'reminder',
                'booking_id' => $booking->id,
            ],
            'reminder'
        );
    }
}
