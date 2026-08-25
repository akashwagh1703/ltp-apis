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
            
            if (empty($userIds)) {
                return response()->json([
                    'message' => 'No users found for target: ' . $request->target
                ], 400);
            }
            
            // Create individual notifications for each user
            $notifications = [];
            foreach ($userIds as $userData) {
                $userId = is_array($userData) ? $userData['id'] : $userData;
                $userType = is_array($userData) ? $userData['type'] : ($request->target === 'owners' ? 'owner' : 'player');
                
                // Skip if userId is null or empty
                if (!$userId) {
                    continue;
                }
                
                $notification = Notification::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'title' => $request->title,
                    'message' => $request->message,
                    'type' => 'general',
                    'target' => $request->target,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'sent_by' => auth()->id(),
                    'data' => [
                        'target' => $request->target,
                        'sent_by' => auth()->id(),
                        'scheduled_at' => $request->scheduled_at,
                    ],
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

    public function index(Request $request)
    {
        return $this->history($request);
    }

    public function sendToAll(Request $request)
    {
        $request->merge([
            'target' => $request->input('target')
                ?: ($request->input('user_type') === 'owner' ? 'owners' : ($request->input('user_type') === 'player' ? 'players' : 'all')),
            'message' => $request->input('message') ?: $request->input('body'),
        ]);

        return $this->send($request);
    }

    public function sendToUser(Request $request, $userId = null)
    {
        $request->merge([
            'message' => $request->input('message') ?: $request->input('body'),
        ]);

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'user_type' => 'required|in:owner,player',
            'user_id' => 'nullable|integer',
        ]);

        $id = $userId ?: $request->input('user_id');
        if (!$id) {
            return response()->json(['message' => 'User ID is required'], 422);
        }

        try {
            $notification = Notification::create([
                'user_id' => $id,
                'user_type' => $request->user_type,
                'title' => $request->title,
                'message' => $request->message,
                'type' => 'general',
                'target' => 'specific',
                'user_ids' => [$id],
                'sent_by' => auth()->id(),
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->sendNotificationToUsers($notification, [$id], $request->user_type);

            return response()->json([
                'message' => 'Notification sent successfully',
                'data' => $notification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
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