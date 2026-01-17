<?php

namespace App\Http\Controllers\Api\V1\Owner;

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

    public function generate(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date',
        ]);

        $turf = Turf::where('id', $request->turf_id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

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

    public function list(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'nullable|date',
        ]);

        $query = TurfSlot::where('turf_id', $request->turf_id);
        
        if ($request->date) {
            $query->where('date', $request->date);
        }

        $slots = $query->orderBy('date')->orderBy('start_time')->get();
        
        // Filter out past slots for today
        $now = \Carbon\Carbon::now();
        $slots = $slots->filter(function($slot) use ($now, $request) {
            // If it's today, only show future slots
            if ($request->date === $now->toDateString()) {
                $slotDateTime = \Carbon\Carbon::parse($request->date . ' ' . $slot->start_time);
                return $slotDateTime->gt($now);
            }
            return true;
        });
        
        // Add is_booked flag and display times
        $slots = $slots->map(function($slot) {
            // Check if slot is booked by status
            $slot->is_booked = in_array($slot->status, ['booked_online', 'booked_offline']);
            
            // If booked, find the booking details
            if ($slot->is_booked) {
                $booking = \App\Models\Booking::where('turf_id', $slot->turf_id)
                    ->where('booking_date', $slot->date)
                    ->where('start_time', '<=', $slot->start_time)
                    ->where('end_time', '>=', $slot->end_time)
                    ->whereIn('booking_status', ['confirmed', 'completed'])
                    ->first();
                
                if ($booking) {
                    $slot->booking = (object)[
                        'id' => $booking->id,
                        'player_name' => $booking->player_name,
                        'booking_status' => $booking->booking_status
                    ];
                }
            }
            
            $slot->start_time_display = \Carbon\Carbon::parse($slot->start_time)->format('g A');
            $slot->end_time_display = \Carbon\Carbon::parse($slot->end_time)->format('g A');
            return $slot;
        })->values();

        \Log::info('Slots retrieved', [
            'turf_id' => $request->turf_id,
            'date' => $request->date,
            'count' => $slots->count(),
        ]);

        return response()->json($slots);
    }

    public function updatePrices(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'nullable|date',
        ]);

        $turf = Turf::with('pricing')
            ->where('id', $request->turf_id)
            ->where('owner_id', auth()->id())
            ->firstOrFail();

        $query = TurfSlot::where('turf_id', $turf->id)
            ->where('status', 'available');
            
        if ($request->date) {
            $query->where('date', $request->date);
        } else {
            // Update future slots only
            $query->where('date', '>=', now()->toDateString());
        }
        
        $slots = $query->get();

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
