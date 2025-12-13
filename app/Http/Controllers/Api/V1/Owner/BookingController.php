<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TurfSlot;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $smsService;
    protected $notificationService;

    public function __construct(SmsService $smsService, NotificationService $notificationService)
    {
        $this->smsService = $smsService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $query = Booking::with(['turf', 'player', 'payment'])
            ->where('owner_id', $request->user()->id);

        if ($request->status) {
            $query->where('booking_status', $request->status);
        }

        if ($request->turf_id) {
            $query->where('turf_id', $request->turf_id);
        }

        if ($request->date) {
            $query->whereDate('booking_date', $request->date);
        }

        $bookings = $query->latest()->paginate(15);

        return BookingResource::collection($bookings);
    }

    public function createOffline(Request $request)
    {
        $request->validate([
            'turf_id' => 'required|exists:turfs,id',
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:turf_slots,id',
            'player_name' => 'required|string|max:255',
            'player_phone' => 'required|string|max:15',
            'booking_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'amount' => 'required|numeric',
            'payment_method' => 'required|in:cash,upi,online,pay_on_turf',
        ]);

        // Get first slot for primary booking
        $firstSlot = TurfSlot::findOrFail($request->slot_ids[0]);
        
        // Check all slots are available
        $slots = TurfSlot::whereIn('id', $request->slot_ids)->get();
        foreach ($slots as $slot) {
            if ($slot->status !== 'available') {
                return response()->json(['message' => 'One or more slots are not available'], 400);
            }
        }

        // Calculate duration
        $startTime = \Carbon\Carbon::parse($request->start_time);
        $endTime = \Carbon\Carbon::parse($request->end_time);
        $duration = $startTime->diffInMinutes($endTime);

        // Create booking for first slot
        $booking = Booking::create([
            'booking_number' => 'BK' . time() . rand(1000, 9999),
            'player_id' => null,
            'turf_id' => $request->turf_id,
            'slot_id' => $firstSlot->id,
            'owner_id' => $request->user()->id,
            'booking_date' => $request->booking_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'slot_duration' => $duration,
            'amount' => $request->amount,
            'discount_amount' => 0,
            'final_amount' => $request->amount,
            'booking_type' => 'offline',
            'booking_status' => 'confirmed',
            'payment_mode' => $request->payment_method,
            'payment_status' => 'success',
            'player_name' => $request->player_name,
            'player_phone' => $request->player_phone,
        ]);

        // Mark all slots as booked and link to booking
        foreach ($slots as $index => $slot) {
            $slot->update(['status' => 'booked_offline']);
            
            // Create additional booking records for other slots to maintain relationship
            if ($slot->id !== $firstSlot->id) {
                Booking::create([
                    'booking_number' => $booking->booking_number . '-' . ($index + 1),
                    'player_id' => null,
                    'turf_id' => $request->turf_id,
                    'slot_id' => $slot->id,
                    'owner_id' => $request->user()->id,
                    'booking_date' => $request->booking_date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'slot_duration' => $duration,
                    'amount' => 0, // Only first booking has amount
                    'discount_amount' => 0,
                    'final_amount' => 0,
                    'booking_type' => 'offline',
                    'booking_status' => 'confirmed',
                    'payment_mode' => $request->payment_method,
                    'payment_status' => 'success',
                    'player_name' => $request->player_name,
                    'player_phone' => $request->player_phone,
                ]);
            }
        }

        try {
            $this->smsService->sendBookingConfirmation(
                $request->player_phone,
                $booking->booking_number,
                $booking->turf->name,
                $booking->booking_date,
                $booking->start_time
            );
        } catch (\Exception $e) {
            \Log::error('SMS sending failed: ' . $e->getMessage());
        }

        return response()->json(new BookingResource($booking->load('turf')), 201);
    }

    public function stats(Request $request)
    {
        $ownerId = $request->user()->id;

        return response()->json([
            'total_bookings' => Booking::where('owner_id', $ownerId)->count(),
            'today_bookings' => Booking::where('owner_id', $ownerId)->whereDate('booking_date', today())->count(),
            'total_revenue' => Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->sum('final_amount'),
            'pending_bookings' => Booking::where('owner_id', $ownerId)->where('payment_status', 'pending')->count(),
        ]);
    }
}
