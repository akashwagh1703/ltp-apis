<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Jobs\SendFcmNotification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = base_path(config('services.fcm.credentials_path'));
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception('Firebase credentials file not found at: ' . $credentialsPath);
        }
        
        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    public function sendToUserAsync($userId, $userType, $title, $body, $data = [], $type = 'general')
    {
        SendFcmNotification::dispatch($userId, $userType, $title, $body, $data, $type);
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
        $notification = FirebaseNotification::create($title, $body);
        $success = true;

        // Send in batches of 500 (FCM limit)
        $batches = array_chunk($tokens, 500);

        foreach ($batches as $batch) {
            try {
                $message = CloudMessage::new()
                    ->withNotification($notification)
                    ->withData($data);

                $report = $this->messaging->sendMulticast($message, $batch);

                // Log successful sends
                foreach ($report->successes()->getItems() as $success) {
                    NotificationLog::create([
                        'user_id' => 0,
                        'user_type' => 'player',
                        'fcm_token' => $success->target()->value(),
                        'status' => 'success',
                        'sent_at' => now(),
                    ]);
                }

                // Remove invalid tokens and log
                foreach ($report->invalidTokens() as $invalidToken) {
                    FcmToken::where('token', $invalidToken)->delete();
                    NotificationLog::create([
                        'user_id' => 0,
                        'user_type' => 'player',
                        'fcm_token' => $invalidToken,
                        'status' => 'invalid_token',
                        'sent_at' => now(),
                    ]);
                    Log::info("Removed invalid FCM token: {$invalidToken}");
                }

                // Log failures
                if ($report->hasFailures()) {
                    foreach ($report->failures()->getItems() as $failure) {
                        NotificationLog::create([
                            'user_id' => 0,
                            'user_type' => 'player',
                            'fcm_token' => $failure->target()->value(),
                            'status' => 'failed',
                            'error_message' => $failure->error()->getMessage(),
                            'sent_at' => now(),
                        ]);
                        Log::error('FCM send failed', [
                            'token' => $failure->target()->value(),
                            'error' => $failure->error()->getMessage()
                        ]);
                    }
                    $success = false;
                }
            } catch (\Exception $e) {
                Log::error('FCM batch send exception: ' . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    public function sendBookingNotification($booking, $toOwner = false, $async = true)
    {
        if ($toOwner) {
            $method = $async ? 'sendToUserAsync' : 'sendToUser';
            return $this->$method(
                $booking->owner_id,
                'owner',
                'New Booking Received',
                "Booking #{$booking->booking_number} for {$booking->turf->name}",
                [
                    'type' => 'booking',
                    'booking_id' => (string)$booking->id,
                    'booking_number' => $booking->booking_number,
                ],
                'booking'
            );
        }

        $method = $async ? 'sendToUserAsync' : 'sendToUser';
        return $this->$method(
            $booking->player_id,
            'player',
            'Booking Confirmed',
            "Your booking #{$booking->booking_number} at {$booking->turf->name} is confirmed",
            [
                'type' => 'booking',
                'booking_id' => (string)$booking->id,
                'booking_number' => $booking->booking_number,
            ],
            'booking'
        );
    }

    public function sendCancellationNotification($booking, $toOwner = false, $async = true)
    {
        if ($toOwner) {
            $method = $async ? 'sendToUserAsync' : 'sendToUser';
            return $this->$method(
                $booking->owner_id,
                'owner',
                'Booking Cancelled',
                "Booking #{$booking->booking_number} has been cancelled",
                [
                    'type' => 'cancellation',
                    'booking_id' => (string)$booking->id,
                ],
                'cancellation'
            );
        }

        $method = $async ? 'sendToUserAsync' : 'sendToUser';
        return $this->$method(
            $booking->player_id,
            'player',
            'Booking Cancelled',
            "Your booking #{$booking->booking_number} has been cancelled",
            [
                'type' => 'cancellation',
                'booking_id' => (string)$booking->id,
            ],
            'cancellation'
        );
    }

    public function sendPaymentNotification($booking, $async = true)
    {
        $method = $async ? 'sendToUserAsync' : 'sendToUser';
        return $this->$method(
            $booking->owner_id,
            'owner',
            'Payment Received',
            "Payment of ₹{$booking->paid_amount} received for booking #{$booking->booking_number}",
            [
                'type' => 'payment',
                'booking_id' => (string)$booking->id,
                'amount' => (string)$booking->paid_amount,
            ],
            'payment'
        );
    }

    public function sendReminderNotification($booking, $async = true)
    {
        $method = $async ? 'sendToUserAsync' : 'sendToUser';
        return $this->$method(
            $booking->player_id,
            'player',
            'Booking Reminder',
            "Your booking at {$booking->turf->name} starts in 2 hours",
            [
                'type' => 'reminder',
                'booking_id' => (string)$booking->id,
            ],
            'reminder'
        );
    }
}
