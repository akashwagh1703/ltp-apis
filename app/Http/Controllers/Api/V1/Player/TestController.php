<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function createTestNotification()
    {
        try {
            $userId = auth()->id();
            
            $notifications = [
                [
                    'title' => 'Welcome to LTP!',
                    'body' => 'Thank you for joining our platform. Start booking your favorite turfs now!',
                    'type' => 'general'
                ],
                [
                    'title' => 'Booking Confirmed',
                    'body' => 'Your booking for Football Turf has been confirmed for tomorrow at 6:00 PM.',
                    'type' => 'booking'
                ],
                [
                    'title' => 'Payment Successful',
                    'body' => 'Your payment of ₹500 has been processed successfully.',
                    'type' => 'payment'
                ],
                [
                    'title' => 'Special Offer!',
                    'body' => 'Get 20% off on your next booking. Use code SAVE20.',
                    'type' => 'promotion'
                ],
                [
                    'title' => 'Booking Reminder',
                    'body' => 'Your booking starts in 1 hour. Don\'t forget to arrive on time!',
                    'type' => 'reminder'
                ]
            ];
            
            $created = [];
            foreach ($notifications as $notif) {
                $created[] = Notification::create([
                    'user_id' => $userId,
                    'user_type' => 'player',
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                    'type' => $notif['type'],
                    'data' => json_encode(['test' => true])
                ]);
            }
            
            return response()->json([
                'message' => 'Test notifications created successfully',
                'count' => count($created)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create test notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}