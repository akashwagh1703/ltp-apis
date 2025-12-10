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

        return response()->json([
            'total_bookings' => Booking::where('owner_id', $ownerId)->count(),
            'today_bookings' => Booking::where('owner_id', $ownerId)->whereDate('booking_date', $today)->count(),
            'month_bookings' => Booking::where('owner_id', $ownerId)->whereDate('booking_date', '>=', $thisMonth)->count(),
            'total_revenue' => Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->sum('final_amount'),
            'today_revenue' => Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->whereDate('booking_date', $today)->sum('final_amount'),
            'month_revenue' => Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->whereDate('booking_date', '>=', $thisMonth)->sum('final_amount'),
            'pending_bookings' => Booking::where('owner_id', $ownerId)->where('payment_status', 'pending')->count(),
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
