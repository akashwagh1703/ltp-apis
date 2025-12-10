<?php

namespace App\Services;

use App\Models\TurfSlot;
use Carbon\Carbon;

class SlotService
{
    public function generateSlots($turfId, $date, $openingTime, $closingTime, $slotDuration, $pricing)
    {
        $slots = [];
        $current = Carbon::parse($date . ' ' . $openingTime);
        $end = Carbon::parse($date . ' ' . $closingTime);

        while ($current->lt($end)) {
            $slotEnd = $current->copy()->addMinutes($slotDuration);
            if ($slotEnd->gt($end)) break;

            $slots[] = [
                'turf_id' => $turfId,
                'date' => $date,
                'start_time' => $current->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
                'price' => 500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $current = $slotEnd;
        }

        return $slots;
    }




}
