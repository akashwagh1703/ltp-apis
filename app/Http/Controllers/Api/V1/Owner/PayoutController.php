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
        $ownerId = $request->user()->id;
        
        // Get all completed bookings for this owner
        $allCompleted = \App\Models\Booking::where('owner_id', $ownerId)
            ->where('booking_status', 'completed')
            ->count();
        
        \Log::info('Unpaid bookings check', [
            'owner_id' => $ownerId,
            'total_completed' => $allCompleted,
        ]);
        
        $bookings = \App\Models\Booking::where('owner_id', $ownerId)
            ->where('booking_type', 'online')
            ->where('booking_status', 'completed')
            ->where('payment_status', 'success')
            ->whereDoesntHave('payoutTransaction')
            ->with(['turf', 'player'])
            ->latest('booking_date')
            ->get();

        // Calculate totals with fallback for old bookings without commission fields
        $totalAmount = 0;
        $totalCommission = 0;
        $totalPayout = 0;
        
        foreach ($bookings as $booking) {
            $amount = $booking->amount ?? 0;
            $totalAmount += $amount;
            
            // If commission fields exist, use them
            if ($booking->platform_commission !== null && $booking->owner_payout !== null) {
                $totalCommission += $booking->platform_commission;
                $totalPayout += $booking->owner_payout;
            } else {
                // Calculate on the fly for old bookings
                $rate = $booking->commission_rate ?? 5.00;
                $commission = ($amount * $rate) / 100;
                $totalCommission += $commission;
                $totalPayout += ($amount - $commission);
            }
        }

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
