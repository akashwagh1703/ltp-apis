<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $booking = Booking::where('player_id', auth()->id())
            ->where('booking_status', 'completed')
            ->findOrFail($request->booking_id);

        $review = Review::create([
            'booking_id' => $booking->id,
            'player_id' => auth()->id(),
            'turf_id' => $booking->turf_id,
            'rating' => $request->rating,
            'review_text' => $request->comment,
            'is_approved' => true,
        ]);

        return response()->json($review, 201);
    }

    public function myReviews()
    {
        $reviews = Review::with('turf')
            ->where('player_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($reviews);
    }
}
