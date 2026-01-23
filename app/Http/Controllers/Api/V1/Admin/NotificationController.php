<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Owner;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'target' => 'required|in:all,owners,players',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        try {
            $userIds = $this->getTargetUserIds($request->target);
            
            // Create individual notifications for each user
            $notifications = [];
            foreach ($userIds as $userData) {
                $userId = is_array($userData) ? $userData['id'] : $userData;
                $userType = is_array($userData) ? $userData['type'] : ($request->target === 'owners' ? 'owner' : 'player');
                
                $notification = Notification::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'title' => $request->title,
                    'body' => $request->message,
                    'type' => 'general',
                    'data' => json_encode([
                        'target' => $request->target,
                        'sent_by' => auth()->id(),
                        'scheduled_at' => $request->scheduled_at
                    ])
                ]);
                
                $notifications[] = $notification;
            }

            return response()->json([
                'message' => 'Notification sent successfully',
                'count' => count($notifications)
            ]);
        } catch (\Exception $e) {
            Log::error('Notification send failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendToUser(Request $request, $userId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'user_type' => 'required|in:owner,player'
        ]);

        try {
            $notification = Notification::create([
                'title' => $request->title,
                'message' => $request->message,
                'target' => 'specific',
                'user_ids' => json_encode([$userId]),
                'user_type' => $request->user_type,
                'sent_by' => auth()->id(),
                'status' => 'sent',
                'sent_at' => now()
            ]);

            $this->sendNotificationToUsers($notification, [$userId], $request->user_type);

            return response()->json([
                'message' => 'Notification sent successfully',
                'data' => $notification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $notifications = Notification::with('sentBy')
            ->when($request->target, function($query, $target) {
                return $query->where('target', $target);
            })
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function stats()
    {
        $stats = [
            'total_sent' => Notification::where('status', 'sent')->count(),
            'scheduled' => Notification::where('status', 'scheduled')->count(),
            'today_sent' => Notification::where('status', 'sent')
                ->whereDate('sent_at', today())->count(),
            'this_week' => Notification::where('status', 'sent')
                ->whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count()
        ];

        return response()->json($stats);
    }

    private function getTargetUserIds($target)
    {
        switch ($target) {
            case 'owners':
                return Owner::where('status', 'active')->pluck('id')->toArray();
            case 'players':
                return Player::pluck('id')->toArray();
            case 'all':
                $owners = Owner::where('status', 'active')->pluck('id')->map(function($id) {
                    return ['id' => $id, 'type' => 'owner'];
                });
                $players = Player::pluck('id')->map(function($id) {
                    return ['id' => $id, 'type' => 'player'];
                });
                return $owners->concat($players)->toArray();
            default:
                return [];
        }
    }

    private function sendNotificationToUsers($notification, $userIds, $userType = null)
    {
        // This would integrate with FCM or push notification service
        // For now, we'll just log the notification
        Log::info('Notification sent', [
            'notification_id' => $notification->id,
            'title' => $notification->title,
            'user_count' => count($userIds),
            'target' => $notification->target
        ]);

        // TODO: Implement actual push notification sending
        // - FCM for mobile apps
        // - WebSocket for real-time updates
        // - Email notifications as fallback
    }
}