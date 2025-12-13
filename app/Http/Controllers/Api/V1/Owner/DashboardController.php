<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $ownerId = $request->user()->id;
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $totalTurfs = \App\Models\Turf::where('owner_id', $ownerId)->count();
        $totalBookings = Booking::where('owner_id', $ownerId)->count();
        $todayBookings = Booking::where('owner_id', $ownerId)->whereDate('booking_date', $today)->count();
        $monthBookings = Booking::where('owner_id', $ownerId)->whereDate('booking_date', '>=', $thisMonth)->count();
        $totalRevenue = Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->sum('final_amount');
        $todayRevenue = Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->whereDate('booking_date', $today)->sum('final_amount');
        $monthRevenue = Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->whereDate('booking_date', '>=', $thisMonth)->sum('final_amount');
        $pendingBookings = Booking::where('owner_id', $ownerId)->where('payment_status', 'pending')->count();

        return response()->json([
            'total_turfs' => $totalTurfs,
            'total_bookings' => $totalBookings,
            'today_bookings' => $todayBookings,
            'month_bookings' => $monthBookings,
            'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            'today_revenue' => number_format($todayRevenue, 2, '.', ''),
            'month_revenue' => number_format($monthRevenue, 2, '.', ''),
            'pending_bookings' => $pendingBookings,
        ]);
    }

    public function recentBookings(Request $request)
    {
        $bookings = Booking::with(['turf', 'player'])
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($bookings);
    }
}
