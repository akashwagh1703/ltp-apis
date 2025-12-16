<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $payouts = Payout::with('transactions')
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return PayoutResource::collection($payouts);
    }

    public function show($id)
    {
        $payout = Payout::with('transactions')
            ->where('id', $id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

        return new PayoutResource($payout);
    }

    public function unpaidBookings(Request $request)
    {
        $bookings = \App\Models\Booking::where('owner_id', $request->user()->id)
            ->where('booking_status', 'completed')
            ->where('payment_status', 'success')
            ->whereDoesntHave('payoutTransaction')
            ->with(['turf', 'player'])
            ->latest('booking_date')
            ->get();

        $totalAmount = $bookings->sum('amount');
        $totalCommission = $bookings->sum('platform_commission');
        $totalPayout = $bookings->sum('owner_payout');

        return response()->json([
            'bookings' => \App\Http\Resources\BookingResource::collection($bookings),
            'summary' => [
                'total_bookings' => $bookings->count(),
                'total_amount' => (float) $totalAmount,
                'commission_amount' => (float) $totalCommission,
                'payout_amount' => (float) $totalPayout,
            ],
        ]);
    }
}
