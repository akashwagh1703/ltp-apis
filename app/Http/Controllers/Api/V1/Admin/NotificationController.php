<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function index(Request $request)
    {
        $query = Notification::with('user')->latest();

        if ($request->user_type) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $notifications = $query->paginate(20);

        return response()->json($notifications);
    }

    public function sendToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:owner,player',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|in:booking,payment,review,promotional,reminder,general',
            'data' => 'nullable|array',
        ]);

        $result = $this->fcmService->sendToUser(
            $request->user_id,
            $request->user_type,
            $request->title,
            $request->body,
            $request->data ?? [],
            $request->type ?? 'general'
        );

        return response()->json([
            'message' => $result ? 'Notification sent successfully' : 'Failed to send notification',
            'success' => $result,
        ]);
    }

    public function sendToAll(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:owner,player',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|in:booking,payment,review,promotional,reminder,general',
            'data' => 'nullable|array',
        ]);

        $result = $this->fcmService->sendToAll(
            $request->user_type,
            $request->title,
            $request->body,
            $request->data ?? [],
            $request->type ?? 'general'
        );

        return response()->json([
            'message' => $result ? 'Notification sent to all users' : 'Failed to send notification',
            'success' => $result,
        ]);
    }
}
