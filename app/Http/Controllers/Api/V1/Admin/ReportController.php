<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function bookings(Request $request)
    {
        $query = Booking::with(['turf', 'player', 'owner'])
            ->where('booking_status', 'completed');

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
        }

        $bookings = $query->get();

        return response()->json([
            'total_bookings' => $bookings->count(),
            'total_revenue' => $bookings->sum('amount'),
            'bookings' => $bookings,
        ]);
    }

    public function turfWise(Request $request)
    {
        $query = Booking::select('turf_id', DB::raw('COUNT(*) as total_bookings'), DB::raw('SUM(amount) as total_revenue'))
            ->with('turf')
            ->where('booking_status', 'completed')
            ->groupBy('turf_id');

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
        }

        return response()->json($query->get());
    }

    public function ownerWise(Request $request)
    {
        $query = Booking::select('owner_id', DB::raw('COUNT(*) as total_bookings'), DB::raw('SUM(amount) as total_revenue'))
            ->with('owner')
            ->where('booking_status', 'completed')
            ->groupBy('owner_id');

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
        }

        return response()->json($query->get());
    }

    public function paymentMode(Request $request)
    {
        $query = Payment::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->where('status', 'success')
            ->groupBy('payment_method');

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
        }

        return response()->json($query->get());
    }
}
