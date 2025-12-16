<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|in:pending,confirmed,completed,cancelled',
                'turf_id' => 'nullable|exists:turfs,id',
                'player_id' => 'nullable|exists:players,id',
                'owner_id' => 'nullable|exists:owners,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:5|max:100',
            ]);

            $query = Booking::with(['turf', 'player', 'payment', 'owner']);

            if (isset($validated['status'])) {
                $query->where('booking_status', $validated['status']);
            }

            if (isset($validated['turf_id'])) {
                $query->where('turf_id', $validated['turf_id']);
            }

            if (isset($validated['player_id'])) {
                $query->where('player_id', $validated['player_id']);
            }

            if (isset($validated['owner_id'])) {
                $query->where('owner_id', $validated['owner_id']);
            }

            if (isset($validated['date_from']) && isset($validated['date_to'])) {
                $query->whereBetween('booking_date', [$validated['date_from'], $validated['date_to']]);
            }

            if (isset($validated['search'])) {
                $query->where(function($q) use ($validated) {
                    $q->where('booking_number', 'like', "%{$validated['search']}%")
                      ->orWhere('player_name', 'like', "%{$validated['search']}%")
                      ->orWhere('player_phone', 'like', "%{$validated['search']}%");
                });
            }

            $perPage = $validated['per_page'] ?? 15;
            $bookings = $query->latest()->paginate($perPage);

            return BookingResource::collection($bookings);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $booking = Booking::with(['turf', 'player', 'payment', 'owner', 'slot'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $booking = Booking::findOrFail($id);

            if ($booking->booking_status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking is already cancelled'
                ], 400);
            }

            if ($booking->booking_status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a completed booking'
                ], 400);
            }
            
            $booking->update([
                'booking_status' => 'cancelled',
                'cancellation_reason' => $validated['reason'],
                'cancelled_by' => 'admin',
                'cancelled_at' => now(),
            ]);

            // Release all slots in the booking time range
            \App\Models\TurfSlot::where('turf_id', $booking->turf_id)
                ->where('date', $booking->booking_date)
                ->where(function($q) use ($booking) {
                    $q->whereBetween('start_time', [$booking->start_time, $booking->end_time])
                      ->orWhereBetween('end_time', [$booking->start_time, $booking->end_time]);
                })
                ->whereIn('status', ['booked_online', 'booked_offline'])
                ->update(['status' => 'available']);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => new BookingResource($booking->fresh())
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
