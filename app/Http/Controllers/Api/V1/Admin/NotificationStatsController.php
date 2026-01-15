<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;

class NotificationStatsController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationLog::query();

        if ($request->from_date) {
            $query->whereDate('sent_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('sent_at', '<=', $request->to_date);
        }

        $total = $query->count();
        $success = (clone $query)->where('status', 'success')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $invalidTokens = (clone $query)->where('status', 'invalid_token')->count();

        return response()->json([
            'total_sent' => $total,
            'successful' => $success,
            'failed' => $failed,
            'invalid_tokens' => $invalidTokens,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ]);
    }

    public function recentFailures(Request $request)
    {
        $failures = NotificationLog::where('status', 'failed')
            ->latest('sent_at')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json($failures);
    }
}
