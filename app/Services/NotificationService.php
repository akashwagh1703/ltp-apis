<?php

namespace App\Services;

use App\Models\Notification;

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
        // FCM push notification implementation
        return true; // Placeholder
    }

    public function sendBookingNotification($booking)
    {
        $this->send(
            'player',
            $booking->player_id,
            'Booking Confirmed',
            "Your booking #{$booking->booking_number} is confirmed.",
            'booking',
            ['booking_id' => $booking->id]
        );

        $this->send(
            'owner',
            $booking->owner_id,
            'New Booking',
            "New booking #{$booking->booking_number} received.",
            'booking',
            ['booking_id' => $booking->id]
        );
    }
}
