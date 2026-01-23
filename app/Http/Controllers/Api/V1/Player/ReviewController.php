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
            'comment' => 'nullable|string|max:500',
        ]);

        $booking = Booking::where('player_id', auth()->id())
            ->where('booking_status', 'completed')
            ->findOrFail($request->booking_id);

        // Check if review already exists
        $existingReview = Review::where('booking_id', $booking->id)->first();
        if ($existingReview) {
            return response()->json(['message' => 'Review already submitted for this booking'], 400);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'player_id' => auth()->id(),
            'turf_id' => $booking->turf_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data' => $review
        ], 201);
    }

    public function myReviews()
    {
        $reviews = Review::with('turf')
            ->where('player_id', auth()->id())
            ->latest()
            ->get()
            ->map(function($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'turf' => $review->turf,
                    'created_at' => $review->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }
}
