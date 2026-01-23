<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\TurfSlot;
use App\Services\SlotService;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    protected $slotService;

    public function __construct(SlotService $slotService)
    {
        $this->slotService = $slotService;
    }

    public function available(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date',
        ]);

        $now = \Carbon\Carbon::now();
        \Log::info('Slots API called', [
            'turf_id' => $request->turf_id,
            'date' => $request->date,
            'current_time' => $now->format('Y-m-d H:i:s'),
            'is_today' => $request->date === $now->toDateString()
        ]);

        $slots = TurfSlot::where('turf_id', $request->turf_id)
            ->where('date', $request->date)
            ->orderBy('start_time')
            ->get();

        \Log::info('Raw slots from DB', [
            'count' => $slots->count(),
            'sample_slots' => $slots->take(3)->map(function($slot) {
                return [
                    'start_time' => $slot->start_time,
                    'status' => $slot->status,
                    'price' => $slot->price
                ];
            })->toArray()
        ]);

        $requestDate = $request->date;
        
        $slots = $slots->filter(function($slot) use ($now, $requestDate) {
            // If the requested date is today, filter by current time
            if ($requestDate === $now->toDateString()) {
                $slotDateTime = \Carbon\Carbon::parse($requestDate . ' ' . $slot->start_time);
                // Show slots that start at least 30 minutes from now
                $bufferTime = $now->copy()->addMinutes(30);
                $isAvailable = $slotDateTime->gt($bufferTime);
                
                \Log::info('Slot time check', [
                    'slot_time' => $slot->start_time,
                    'slot_datetime' => $slotDateTime->format('Y-m-d H:i:s'),
                    'buffer_time' => $bufferTime->format('Y-m-d H:i:s'),
                    'is_available' => $isAvailable
                ]);
                
                return $isAvailable;
            }
            // For future dates, show all slots
            return true;
        });

        $slots = $slots->map(function($slot) {
            // Check if slot is booked by status
            $slot->is_booked = in_array($slot->status, ['booked_online', 'booked_offline']);
            $slot->start_time_display = \Carbon\Carbon::parse($slot->start_time)->format('g A');
            $slot->end_time_display = \Carbon\Carbon::parse($slot->end_time)->format('g A');
            return $slot;
        })->values();

        \Log::info('Final slots returned', [
            'count' => $slots->count(),
            'sample_times' => $slots->take(5)->pluck('start_time_display')->toArray()
        ]);

        return response()->json($slots);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date',
        ]);

        $turf = Turf::findOrFail($request->turf_id);

        $slots = $this->slotService->generateSlots(
            $turf->id,
            $request->date,
            $turf->opening_time,
            $turf->closing_time,
            $turf->slot_duration,
            $turf->uniform_price
        );

        TurfSlot::insert($slots);

        return response()->json(['message' => 'Slots generated successfully', 'count' => count($slots)]);
    }

    public function updatePrices(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
        ]);

        $turf = Turf::with('pricing')->findOrFail($request->turf_id);
        $slots = TurfSlot::where('turf_id', $turf->id)
            ->where('status', 'available')
            ->get();

        $updated = 0;
        foreach ($slots as $slot) {
            $dayType = \Carbon\Carbon::parse($slot->date)->isWeekend() ? 'weekend' : 'weekday';
            $slotTime = \Carbon\Carbon::parse($slot->start_time);
            $price = $this->calculateSlotPrice($turf, $dayType, $slotTime);
            
            if ($slot->price != $price) {
                $slot->price = $price;
                $slot->save();
                $updated++;
            }
        }

        return response()->json(['message' => 'Prices updated successfully', 'updated' => $updated]);
    }

    private function calculateSlotPrice($turf, $dayType, $slotTime)
    {
        if ($turf->pricing_type === 'uniform') {
            return $turf->uniform_price ?? 500.00;
        }

        $hour = $slotTime->hour;
        if ($hour >= 6 && $hour < 12) $timeSlot = 'morning';
        elseif ($hour >= 12 && $hour < 17) $timeSlot = 'afternoon';
        elseif ($hour >= 17 && $hour < 21) $timeSlot = 'evening';
        else $timeSlot = 'night';

        $pricing = $turf->pricing->where('day_type', $dayType)
            ->where('time_slot', $timeSlot)
            ->first();

        return $pricing ? $pricing->price : ($turf->uniform_price ?? 500.00);
    }
}
