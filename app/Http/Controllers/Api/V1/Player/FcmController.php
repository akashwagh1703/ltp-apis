<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use App\Models\Notification;
use Illuminate\Http\Request;

class FcmController extends Controller
{
    public function registerToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        FcmToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'user_type' => 'player',
                'token' => $request->token,
            ],
            [
                'device_type' => $request->device_type ?? 'android',
            ]
        );

        return response()->json(['message' => 'Token registered successfully']);
    }

    public function getNotifications(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->where('user_type', 'player')
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('user_type', 'player')
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('user_type', 'player')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
