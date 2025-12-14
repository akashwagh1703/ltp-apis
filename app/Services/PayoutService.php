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
            ->where('booking_status', 'completed')
            ->whereBetween('booking_date', [$periodStart, $periodEnd])
            ->where('payment_status', 'success')
            ->get();

        $totalAmount = $bookings->sum('amount');
        $commissionAmount = $bookings->sum('platform_commission');
        $settlementAmount = $bookings->sum('owner_payout');
        $commissionRate = $bookings->first()->commission_rate ?? 5.00;

        $payout = Payout::create([
            'owner_id' => $ownerId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_bookings' => $bookings->count(),
            'total_amount' => $totalAmount,
            'commission_percentage' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'settlement_amount' => $settlementAmount,
            'status' => 'pending',
        ]);

        foreach ($bookings as $booking) {
            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'booking_id' => $booking->id,
                'booking_amount' => $booking->amount,
                'commission_rate' => $booking->commission_rate,
                'commission_amount' => $booking->platform_commission,
                'owner_amount' => $booking->owner_payout,
            ]);
        }

        return $payout;
    }
}
