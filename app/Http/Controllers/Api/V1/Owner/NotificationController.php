<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_type', 'owner')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_type', 'owner')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        Notification::where('user_type', 'owner')
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All marked as read']);
    }
}
