<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Owner;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send($userType, $userId, $title, $message, $type = 'general', $data = [])
    {
        Notification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);

        $this->sendPushNotification($userType, $userId, $title, $message, $data);
    }

    private function sendPushNotification($userType, $userId, $title, $message, $data)
    {
        try {
            $fcmToken = null;
            
            if ($userType === 'owner') {
                $user = Owner::find($userId);
                $fcmToken = $user?->fcm_token;
            } elseif ($userType === 'player') {
                $user = Player::find($userId);
                $fcmToken = $user?->fcm_token;
            }

            if (!$fcmToken) {
                Log::info("No FCM token for {$userType} {$userId}");
                return false;
            }

            $serverKey = env('FCM_SERVER_KEY');
            if (!$serverKey) {
                Log::error('FCM_SERVER_KEY not configured');
                return false;
            }

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data' => array_merge($data, [
                    'type' => $data['type'] ?? 'general',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ]),
                'priority' => 'high',
            ]);

            if ($response->successful()) {
                Log::info("FCM notification sent to {$userType} {$userId}");
                return true;
            } else {
                Log::error("FCM send failed: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("FCM error: " . $e->getMessage());
            return false;
        }
    }

    public function sendBookingNotification($booking)
    {
        $this->send(
            'player',
            $booking->player_id,
            'Booking Confirmed',
            "Your booking #{$booking->booking_number} is confirmed.",
            'booking',
            ['booking_id' => $booking->id, 'type' => 'booking']
        );

        $this->send(
            'owner',
            $booking->owner_id,
            'New Booking',
            "New booking #{$booking->booking_number} received.",
            'booking',
            ['booking_id' => $booking->id, 'type' => 'booking']
        );
    }
}
