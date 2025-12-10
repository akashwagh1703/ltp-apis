<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['player', 'turf', 'booking'])
            ->whereHas('turf', function ($q) {
                $q->where('owner_id', auth()->id());
            });

        if ($request->turf_id) {
            $query->where('turf_id', $request->turf_id);
        }

        $reviews = $query->latest()->paginate(15);

        return response()->json($reviews);
    }
}
