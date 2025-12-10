<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\TurfSlot;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\SlotService;
use App\Services\SmsService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $slotService;
    protected $paymentService;
    protected $smsService;
    protected $notificationService;

    public function __construct(
        SlotService $slotService,
        PaymentService $paymentService,
        SmsService $smsService,
        NotificationService $notificationService
    ) {
        $this->slotService = $slotService;
        $this->paymentService = $paymentService;
        $this->smsService = $smsService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $bookings = Booking::with(['turf', 'payment'])
            ->where('player_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }

    public function store(Request $request)
    {
        $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:turf_slots,id',
        ]);

        $slots = TurfSlot::with('turf')
            ->whereIn('id', $request->slot_ids)
            ->orderBy('start_time')
            ->get();

        if ($slots->isEmpty()) {
            return response()->json(['message' => 'No slots found'], 400);
        }

        foreach ($slots as $slot) {
            if ($slot->status !== 'available') {
                return response()->json(['message' => 'One or more slots not available'], 400);
            }
        }

        $player = $request->user();
        $firstSlot = $slots->first();
        $lastSlot = $slots->last();
        $totalAmount = $slots->sum('price');
        $duration = $slots->count() * 60;

        $booking = Booking::create([
            'booking_number' => 'BK' . time() . rand(1000, 9999),
            'player_id' => $player->id,
            'turf_id' => $firstSlot->turf_id,
            'slot_id' => $firstSlot->id,
            'owner_id' => $firstSlot->turf->owner_id,
            'booking_date' => $firstSlot->date,
            'start_time' => $firstSlot->start_time,
            'end_time' => $lastSlot->end_time,
            'slot_duration' => $duration,
            'amount' => $totalAmount,
            'discount_amount' => 0,
            'final_amount' => $totalAmount,
            'booking_type' => 'online',
            'booking_status' => 'confirmed',
            'payment_mode' => 'online',
            'payment_status' => 'pending',
            'player_name' => $player->name ?? 'Guest',
            'player_phone' => $player->phone,
            'player_email' => $player->email,
        ]);

        TurfSlot::whereIn('id', $request->slot_ids)->update(['status' => 'booked_online']);

        return response()->json(new BookingResource($booking), 201);
    }

    public function confirmPayment(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $booking->update(['payment_status' => 'success']);

        return response()->json(['message' => 'Payment confirmed']);
    }

    public function cancel($id)
    {
        $booking = Booking::where('player_id', auth()->id())
            ->findOrFail($id);

        $booking->update([
            'booking_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => 'player',
        ]);

        TurfSlot::where('id', $booking->slot_id)->update(['status' => 'available']);

        return response()->json(['message' => 'Booking cancelled']);
    }
}
