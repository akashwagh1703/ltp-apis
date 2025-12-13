<?php

namespace App\Services;

use App\Models\Turf;
use App\Models\TurfPricing;
use App\Models\TurfSlot;
use Carbon\Carbon;

class SlotService
{
    public function generateSlots($turfId, $date, $openingTime, $closingTime, $slotDuration, $uniformPrice = null)
    {
        $turf = Turf::with('pricing')->find($turfId);
        $slots = [];
        $current = Carbon::parse($date . ' ' . $openingTime);
        $end = Carbon::parse($date . ' ' . $closingTime);
        $dayType = Carbon::parse($date)->isWeekend() ? 'weekend' : 'weekday';

        while ($current->lt($end)) {
            $slotEnd = $current->copy()->addMinutes($slotDuration);
            if ($slotEnd->gt($end)) break;

            $price = $this->calculatePrice($turf, $uniformPrice, $dayType, $current);

            $slots[] = [
                'turf_id' => $turfId,
                'date' => $date,
                'start_time' => $current->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
                'price' => $price,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $current = $slotEnd;
        }

        return $slots;
    }

    private function calculatePrice($turf, $uniformPrice, $dayType, $slotTime)
    {
        if (!$turf || $turf->pricing_type === 'uniform') {
            return $uniformPrice ?? 500.00;
        }

        $hour = $slotTime->hour;
        $timeSlot = $this->getTimeSlot($hour);

        $pricing = TurfPricing::where('turf_id', $turf->id)
            ->where('day_type', $dayType)
            ->where('time_slot', $timeSlot)
            ->first();

        return $pricing ? $pricing->price : ($uniformPrice ?? 500.00);
    }

    private function getTimeSlot($hour)
    {
        if ($hour >= 6 && $hour < 12) return 'morning';
        if ($hour >= 12 && $hour < 17) return 'afternoon';
        if ($hour >= 17 && $hour < 21) return 'evening';
        return 'night';
    }
}
