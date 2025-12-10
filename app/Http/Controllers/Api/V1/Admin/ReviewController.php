<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['player', 'turf', 'booking']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->turf_id) {
            $query->where('turf_id', $request->turf_id);
        }

        $reviews = $query->latest()->paginate(15);

        return response()->json($reviews);
    }

    public function updateStatus(Request $request, $id)
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => $request->status]);
        return response()->json(['message' => 'Status updated']);
    }

    public function destroy($id)
    {
        Review::findOrFail($id)->delete();
        return response()->json(['message' => 'Review deleted']);
    }
}
