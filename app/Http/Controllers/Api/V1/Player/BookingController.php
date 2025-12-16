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
            'coupon_code' => 'nullable|string'
        ]);

        try {
            \DB::beginTransaction();

            $slots = TurfSlot::with('turf')
                ->whereIn('id', $request->slot_ids)
                ->orderBy('start_time')
                ->lockForUpdate()
                ->get();

            if ($slots->isEmpty()) {
                return response()->json(['message' => 'No slots found'], 400);
            }

            // Validate all slots are from same turf and same date
            $turfId = $slots->first()->turf_id;
            $date = $slots->first()->date instanceof \Carbon\Carbon 
                ? $slots->first()->date->format('Y-m-d') 
                : $slots->first()->date;
            
            foreach ($slots as $slot) {
                if ($slot->turf_id !== $turfId) {
                    return response()->json(['message' => 'All slots must be from the same turf'], 400);
                }
                
                $slotDate = $slot->date instanceof \Carbon\Carbon 
                    ? $slot->date->format('Y-m-d') 
                    : $slot->date;
                    
                if ($slotDate !== $date) {
                    \Log::error('Date mismatch', ['slot_date' => $slotDate, 'expected_date' => $date]);
                    return response()->json(['message' => 'All slots must be for the same date'], 400);
                }
                
                if ($slot->status !== 'available') {
                    return response()->json(['message' => 'One or more slots not available'], 400);
                }
            }

            $player = $request->user();
            $firstSlot = $slots->first();
            $lastSlot = $slots->last();
            $totalAmount = $slots->sum('price');
            $duration = $slots->count() * 60;
            $discountAmount = 0;

            // Apply coupon if provided
            if ($request->coupon_code) {
                $coupon = \App\Models\Coupon::where('code', $request->coupon_code)
                    ->where('status', 'active')
                    ->where('valid_from', '<=', now())
                    ->where('valid_to', '>=', now())
                    ->first();
                
                if ($coupon && $totalAmount >= $coupon->min_booking_amount) {
                    if ($coupon->discount_type === 'percentage') {
                        $discountAmount = ($totalAmount * $coupon->discount_value) / 100;
                        if ($coupon->max_discount_amount) {
                            $discountAmount = min($discountAmount, $coupon->max_discount_amount);
                        }
                    } else {
                        $discountAmount = $coupon->discount_value;
                    }
                }
            }

            $finalAmount = $totalAmount - $discountAmount;
            
            // Get owner-specific commission rate (or platform default)
            $owner = $firstSlot->turf->owner;
            $commissionRatePercent = $owner->getCommissionRate(); // e.g., 5.00
            $commissionRate = $commissionRatePercent / 100; // Convert 5.00 to 0.05
            $platformCommission = $totalAmount * $commissionRate;
            $ownerPayout = $totalAmount - $platformCommission;

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
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'platform_commission' => $platformCommission,
                'owner_payout' => $ownerPayout,
                'commission_rate' => $commissionRate * 100, // Store as 5.00
                'booking_type' => 'online',
                'booking_status' => 'confirmed',
                'payment_mode' => 'online',
                'payment_status' => 'success',
                'player_name' => $player->name ?? 'Guest',
                'player_phone' => $player->phone,
                'player_email' => $player->email,
            ]);

            // Mark all slots as booked
            TurfSlot::whereIn('id', $request->slot_ids)->update(['status' => 'booked_online']);

            // Send notification to owner
            app(NotificationService::class)->sendBookingNotification($booking);

            \DB::commit();
            return response()->json(new BookingResource($booking->load('turf', 'payment')), 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Booking failed: ' . $e->getMessage()], 500);
        }
    }

    public function confirmPayment(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $booking->update(['payment_status' => 'success']);

        return response()->json(['message' => 'Payment confirmed']);
    }

    public function cancel(Request $request, $id)
    {
        try {
            \DB::beginTransaction();

            $booking = Booking::where('player_id', auth()->id())
                ->findOrFail($id);

            if ($booking->booking_status === 'cancelled') {
                return response()->json(['message' => 'Booking already cancelled'], 400);
            }

            if ($booking->booking_status === 'completed') {
                return response()->json(['message' => 'Cannot cancel completed booking'], 400);
            }

            $booking->update([
                'booking_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'player',
                'cancellation_reason' => $request->reason ?? 'Cancelled by player'
            ]);

            // Release all booked slots in the booking time range
            TurfSlot::where('turf_id', $booking->turf_id)
                ->where('date', $booking->booking_date)
                ->where(function($q) use ($booking) {
                    $q->whereBetween('start_time', [$booking->start_time, $booking->end_time])
                      ->orWhereBetween('end_time', [$booking->start_time, $booking->end_time]);
                })
                ->whereIn('status', ['booked_online', 'booked_offline'])
                ->update(['status' => 'available']);

            \DB::commit();
            return response()->json(['message' => 'Booking cancelled successfully']);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }
}
