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

        $slots = TurfSlot::where('turf_id', $request->turf_id)
            ->where('date', $request->date)
            ->orderBy('start_time')
            ->get();

        $now = \Carbon\Carbon::now();
        $slots = $slots->filter(function($slot) use ($now, $request) {
            if ($request->date === $now->toDateString()) {
                $slotDateTime = \Carbon\Carbon::parse($request->date . ' ' . $slot->start_time);
                return $slotDateTime->gt($now);
            }
            return true;
        });

        $slots = $slots->map(function($slot) {
            // Check if slot is booked by status
            $slot->is_booked = in_array($slot->status, ['booked_online', 'booked_offline']);
            $slot->start_time_display = \Carbon\Carbon::parse($slot->start_time)->format('g A');
            $slot->end_time_display = \Carbon\Carbon::parse($slot->end_time)->format('g A');
            return $slot;
        })->values();

        \Log::info('Slots returned', [
            'turf_id' => $request->turf_id,
            'date' => $request->date,
            'count' => $slots->count(),
            'sample_prices' => $slots->take(3)->pluck('price')->toArray()
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
