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
        $totalRevenue = Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->sum('final_amount');
        $pendingBookings = Booking::where('owner_id', $ownerId)->where('payment_status', 'pending')->count();

        // Online bookings stats
        $onlineBookings = Booking::where('owner_id', $ownerId)->where('booking_type', 'online')->count();
        $onlineRevenue = Booking::where('owner_id', $ownerId)
            ->where('booking_type', 'online')
            ->where('booking_status', 'completed')
            ->sum('final_amount');

        // Offline bookings stats
        $offlineBookings = Booking::where('owner_id', $ownerId)->where('booking_type', 'offline')->count();
        $offlineRevenue = Booking::where('owner_id', $ownerId)
            ->where('booking_type', 'offline')
            ->where('booking_status', 'completed')
            ->sum('final_amount');

        // Payment stats
        $paidAmount = Booking::where('owner_id', $ownerId)
            ->where('payment_status', 'success')
            ->where('booking_status', 'completed')
            ->sum('final_amount');
        $pendingAmount = Booking::where('owner_id', $ownerId)
            ->where('payment_status', 'pending')
            ->sum('final_amount');

        return response()->json([
            'total_turfs' => $totalTurfs,
            'total_bookings' => $totalBookings,
            'today_bookings' => $todayBookings,
            'total_revenue' => number_format($totalRevenue, 2, '.', ''),
            'pending_bookings' => $pendingBookings,
            'online_bookings' => $onlineBookings,
            'online_revenue' => number_format($onlineRevenue, 2, '.', ''),
            'offline_bookings' => $offlineBookings,
            'offline_revenue' => number_format($offlineRevenue, 2, '.', ''),
            'paid_amount' => number_format($paidAmount, 2, '.', ''),
            'pending_amount' => number_format($pendingAmount, 2, '.', ''),
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
