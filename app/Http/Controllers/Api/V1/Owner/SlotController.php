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
            null
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

        $query = TurfSlot::with(['booking' => function($q) {
            $q->select('id', 'slot_id', 'player_name', 'booking_status');
        }])
        ->where('turf_id', $request->turf_id);
        
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
            $slot->is_booked = $slot->booking !== null && in_array($slot->booking->booking_status, ['confirmed', 'completed']);
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
}
