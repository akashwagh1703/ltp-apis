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
            'turf_slot_id' => 'required|exists:turf_slots,id',
            'player_name' => 'required|string|max:255',
            'player_phone' => 'required|string|max:15',
        ]);

        $slot = TurfSlot::findOrFail($request->turf_slot_id);

        if ($slot->status !== 'available') {
            return response()->json(['message' => 'Slot not available'], 400);
        }

        $booking = Booking::create([
            'booking_number' => 'BK' . time() . rand(1000, 9999),
            'player_id' => null,
            'turf_id' => $request->turf_id,
            'slot_id' => $slot->id,
            'owner_id' => $request->user()->id,
            'booking_date' => $slot->date,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'slot_duration' => 60,
            'amount' => $slot->price,
            'discount_amount' => 0,
            'final_amount' => $slot->price,
            'booking_type' => 'offline',
            'booking_status' => 'confirmed',
            'payment_mode' => 'cash',
            'payment_status' => 'success',
            'player_name' => $request->player_name,
            'player_phone' => $request->player_phone,
        ]);

        TurfSlot::where('id', $slot->id)->update(['status' => 'booked_offline']);

        $this->smsService->sendBookingConfirmation(
            $request->player_phone,
            $booking->booking_number,
            $booking->turf->name,
            $booking->booking_date->format('d M Y'),
            $booking->start_time
        );

        return response()->json(new BookingResource($booking), 201);
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
