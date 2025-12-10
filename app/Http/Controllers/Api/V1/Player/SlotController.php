<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\TurfSlot;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function available(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'date' => 'required|date',
        ]);

        $slots = TurfSlot::where('turf_id', $request->turf_id)
            ->where('date', $request->date)
            ->where('status', 'available')
            ->orderBy('start_time')
            ->get();

        return response()->json($slots);
    }
}
