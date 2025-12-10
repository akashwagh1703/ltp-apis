<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Models\Booking;
use Carbon\Carbon;

class PayoutService
{
    public function generatePayout($ownerId, $periodStart, $periodEnd)
    {
        $bookings = Booking::where('owner_id', $ownerId)
            ->where('status', 'completed')
            ->whereBetween('booking_date', [$periodStart, $periodEnd])
            ->whereHas('payment', function ($q) {
                $q->where('status', 'completed');
            })
            ->get();

        $totalRevenue = $bookings->sum('amount');
        $commissionRate = config('app.commission_percentage', 10);
        $commissionAmount = ($totalRevenue * $commissionRate) / 100;
        $payoutAmount = $totalRevenue - $commissionAmount;

        $payout = Payout::create([
            'owner_id' => $ownerId,
            'payout_number' => 'PO' . time() . rand(1000, 9999),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_bookings' => $bookings->count(),
            'total_revenue' => $totalRevenue,
            'commission_amount' => $commissionAmount,
            'payout_amount' => $payoutAmount,
            'status' => 'pending',
        ]);

        foreach ($bookings as $booking) {
            $bookingCommission = ($booking->amount * $commissionRate) / 100;
            
            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'booking_id' => $booking->id,
                'booking_amount' => $booking->amount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $bookingCommission,
                'owner_amount' => $booking->amount - $bookingCommission,
            ]);
        }

        return $payout;
    }
}
