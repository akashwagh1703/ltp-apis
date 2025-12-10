<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Owner;
use App\Models\Player;
use App\Models\Turf;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_turfs' => Turf::where('status', '!=', 'deleted')->count(),
                    'active_turfs' => Turf::where('status', 'approved')->count(),
                    'pending_turfs' => Turf::where('status', 'pending')->count(),
                    'total_owners' => Owner::where('status', '!=', 'deleted')->count(),
                    'active_owners' => Owner::where('status', 'active')->count(),
                    'total_players' => Player::where('status', '!=', 'deleted')->count(),
                    'active_players' => Player::where('status', 'active')->count(),
                    'total_bookings' => Booking::count(),
                    'today_bookings' => Booking::whereDate('booking_date', $today)->count(),
                    'month_bookings' => Booking::whereDate('booking_date', '>=', $thisMonth)->count(),
                    'total_revenue' => (float) Booking::where('booking_status', 'completed')->sum('amount'),
                    'today_revenue' => (float) Booking::where('booking_status', 'completed')->whereDate('booking_date', $today)->sum('amount'),
                    'month_revenue' => (float) Booking::where('booking_status', 'completed')->whereDate('booking_date', '>=', $thisMonth)->sum('amount'),
                    'pending_bookings' => Booking::where('booking_status', 'pending')->count(),
                    'cancelled_bookings' => Booking::where('booking_status', 'cancelled')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recentBookings()
    {
        try {
            $bookings = Booking::with(['turf', 'player', 'owner'])
                ->latest()
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
