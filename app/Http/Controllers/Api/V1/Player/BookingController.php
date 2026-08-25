<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\TurfSlot;
use App\Services\CouponService;
use App\Services\FcmService;
use App\Services\SlotService;
use App\Services\SmsService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $slotService;
    protected $smsService;
    protected $fcmService;

    public function __construct(
        SlotService $slotService,
        SmsService $smsService,
        FcmService $fcmService
    ) {
        $this->slotService = $slotService;
        $this->smsService = $smsService;
        $this->fcmService = $fcmService;
    }

    public function index(Request $request)
    {
        $bookings = Booking::with(['turf', 'payment', 'owner'])
            ->where('player_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }

    public function show(Request $request, $id)
    {
        $booking = Booking::with(['turf', 'payment', 'owner'])
            ->where('player_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => (new BookingResource($booking))->resolve(),
            'payment_instructions' => $booking->needsOwnerPaymentConfirm()
                ? $booking->paymentInstructions()
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:turf_slots,id',
            'pay_method' => 'nullable|in:upi,pay_on_arrival,upi_advance',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $payMethod = $request->input('pay_method', 'upi');

        try {
            \DB::beginTransaction();

            $slots = TurfSlot::with(['turf.owner'])
                ->whereIn('id', $request->slot_ids)
                ->orderBy('start_time')
                ->lockForUpdate()
                ->get();

            if ($slots->isEmpty()) {
                return response()->json(['message' => 'No slots found'], 400);
            }

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
            $turf = $firstSlot->turf;
            if (!$turf || !$turf->canTakeBookings()) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TURF_NOT_APPROVED',
                        'message' => 'This turf is not live yet. Bookings open after LTP approves it.',
                    ],
                    'message' => 'This turf is not live yet. Bookings open after LTP approves it.',
                ], 403);
            }
            $owner = $turf->owner;
            if (!$owner || !$owner->hasUpiSetup()) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UPI_REQUIRED',
                        'message' => 'This turf cannot take bookings until the owner adds UPI.',
                    ],
                    'message' => 'This turf cannot take bookings until the owner adds UPI.',
                ], 403);
            }
            if (!$owner->canAcceptOnlineBookings()) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SUBSCRIPTION_EXPIRED',
                        'message' => 'This turf is not taking online bookings right now.',
                    ],
                    'message' => 'This turf is not taking online bookings right now.',
                ], 403);
            }
            $commissionRatePercent = $owner ? $owner->getCommissionRate() : 5.00;
            $commissionRate = $commissionRatePercent / 100;

            $discountAmount = 0;
            $coupon = null;
            $finalAmount = (float) $totalAmount;
            if ($request->filled('coupon_code')) {
                $couponService = app(CouponService::class);
                $coupon = $couponService->findUsable($request->coupon_code);
                $discountAmount = $couponService->discountFor($coupon, $finalAmount);
                $finalAmount = round($finalAmount - $discountAmount, 2);
            }

            $advancePercent = null;
            if ($payMethod === 'upi_advance') {
                $advancePercent = Setting::getBookingAdvancePercent();
                if ($advancePercent <= 0) {
                    \DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pay part now is not offered right now. Pay the full amount or pay at the turf.',
                    ], 400);
                }
            }

            $platformCommission = $finalAmount * $commissionRate;
            $ownerPayout = $finalAmount - $platformCommission;

            $isPayOnArrival = $payMethod === 'pay_on_arrival';
            $holdMethod = $payMethod === 'upi_advance' ? 'upi' : $payMethod;
            $bookingStatus = $isPayOnArrival
                ? Booking::STATUS_PAY_ON_ARRIVAL
                : Booking::STATUS_AWAITING_CONFIRMATION;
            $paymentMode = $isPayOnArrival
                ? Booking::PAY_MODE_PAY_ON_ARRIVAL
                : Booking::PAY_MODE_UPI;

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
                'coupon_id' => $coupon?->id,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'paid_amount' => 0,
                'pending_amount' => $finalAmount,
                'advance_percentage' => $advancePercent,
                'platform_commission' => $platformCommission,
                'owner_payout' => $ownerPayout,
                'commission_rate' => $commissionRate * 100,
                'booking_type' => 'online',
                'booking_status' => $bookingStatus,
                'payment_mode' => $paymentMode,
                'payment_status' => 'pending',
                'player_name' => $player->name ?? 'Guest',
                'player_phone' => $player->phone,
                'player_email' => $player->email,
                'payment_hold_expires_at' => Booking::holdExpiresAt(
                    $holdMethod,
                    $firstSlot->date,
                    $firstSlot->start_time
                ),
            ]);

            if ($coupon) {
                $coupon->increment('used_count');
            }

            TurfSlot::whereIn('id', $request->slot_ids)->update(['status' => 'booked_online']);

            \DB::commit();

            $booking->load(['turf', 'payment', 'owner']);

            try {
                $this->fcmService->sendBookingNotification($booking, true);
            } catch (\Exception $e) {
                \Log::warning('FCM notification failed: ' . $e->getMessage());
            }

            $turfName = $booking->turf?->name ?? 'the turf';
            $due = $booking->dueNowAmount();
            if ($isPayOnArrival) {
                $message = "Slot held. Pay ₹{$booking->final_amount} at {$turfName} when you arrive.";
            } elseif ($booking->isAdvancePay()) {
                $rest = $booking->final_amount - $due;
                $message = "Pay ₹{$due} now. ₹{$rest} at {$turfName}.";
            } else {
                $message = "Pay ₹{$booking->final_amount} to {$turfName}";
            }

            return response()->json([
                'success' => true,
                'data' => (new BookingResource($booking))->resolve(),
                'payment_required' => !$isPayOnArrival,
                'payment_instructions' => $booking->paymentInstructions(),
                'message' => $message,
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Booking failed: ' . $e->getMessage()], 500);
        }
    }

    public function markPaid(Request $request, $id)
    {
        $booking = Booking::with(['turf', 'owner'])
            ->where('player_id', $request->user()->id)
            ->findOrFail($id);

        if ($booking->booking_status === Booking::STATUS_EXPIRED) {
            return response()->json(['message' => 'This booking has expired'], 400);
        }

        if (!$booking->isAwaitingConfirmation()) {
            return response()->json(['message' => 'This booking is not waiting for UPI payment'], 400);
        }

        $grace = \App\Models\Setting::getBookingConfirmGraceMinutes();
        $booking->update([
            'marked_paid_at' => now(),
            'payment_hold_expires_at' => now()->addMinutes($grace),
        ]);

        try {
            $this->fcmService->sendPaymentNotification($booking->fresh());
        } catch (\Exception $e) {
            \Log::warning('FCM mark-paid notification failed: ' . $e->getMessage());
        }

        $turfName = $booking->turf?->name ?? 'the turf';

        return response()->json([
            'success' => true,
            'data' => (new BookingResource($booking->fresh(['turf', 'owner', 'payment'])))->resolve(),
            'message' => "Waiting for {$turfName} to confirm…",
        ]);
    }

    public function payOnArrival(Request $request, $id)
    {
        $booking = Booking::with(['turf', 'owner'])
            ->where('player_id', $request->user()->id)
            ->findOrFail($id);

        if ($booking->booking_status === Booking::STATUS_PAY_ON_ARRIVAL) {
            return response()->json([
                'success' => true,
                'data' => (new BookingResource($booking))->resolve(),
                'payment_instructions' => $booking->paymentInstructions(),
                'message' => 'Pay when you arrive at the turf.',
            ]);
        }

        if (!$booking->isAwaitingConfirmation()) {
            return response()->json(['message' => 'Cannot switch this booking to pay at the turf'], 400);
        }

        $booking->update([
            'booking_status' => Booking::STATUS_PAY_ON_ARRIVAL,
            'payment_mode' => Booking::PAY_MODE_PAY_ON_ARRIVAL,
            'marked_paid_at' => null,
            'payment_hold_expires_at' => Booking::holdExpiresAt(
                'pay_on_arrival',
                $booking->booking_date,
                $booking->start_time
            ),
        ]);

        $booking->load(['turf', 'owner', 'payment']);
        $turfName = $booking->turf?->name ?? 'the turf';

        return response()->json([
            'success' => true,
            'data' => (new BookingResource($booking))->resolve(),
            'payment_instructions' => $booking->paymentInstructions(),
            'message' => "Pay ₹{$booking->final_amount} at {$turfName} when you arrive.",
        ]);
    }

    public function confirmPayment(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'GONE',
                'message' => 'Pay the turf by UPI QR, then tap I have paid. The owner confirms Received.',
            ],
        ], 410);
    }

    public function cancel(Request $request, $id)
    {
        try {
            \DB::beginTransaction();

            $booking = Booking::with(['turf', 'owner'])
                ->where('player_id', auth()->id())
                ->findOrFail($id);

            if ($booking->booking_status === 'cancelled') {
                return response()->json(['message' => 'Booking already cancelled'], 400);
            }

            if (!in_array($booking->booking_status, [
                Booking::STATUS_AWAITING_CONFIRMATION,
                Booking::STATUS_PAY_ON_ARRIVAL,
                Booking::STATUS_CONFIRMED,
            ], true)) {
                return response()->json(['message' => 'Cannot cancel this booking'], 400);
            }

            $booking->update([
                'booking_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'player',
                'cancellation_reason' => $request->reason ?? 'Cancelled by player'
            ]);

            $booking->releaseHeldSlots();

            \DB::commit();
            
            // Send FCM notifications
            try {
                $this->fcmService->sendCancellationNotification($booking, false);
                $this->fcmService->sendCancellationNotification($booking, true);
            } catch (\Exception $e) {
                \Log::warning('FCM notification failed: ' . $e->getMessage());
            }
            
            // Send WhatsApp notifications (non-blocking)
            try {
                if (class_exists('\App\Services\WhatsAppService')) {
                    $whatsappService = new \App\Services\WhatsAppService();
                    
                    // Notify player
                    $whatsappService->sendCancellationToPlayer(
                        $booking->player_phone,
                        [
                            'booking_number' => $booking->booking_number,
                            'turf_name' => $booking->turf->name,
                            'booking_date' => $booking->booking_date->format('d M Y'),
                            'start_time' => $booking->start_time,
                            'cancellation_reason' => $booking->cancellation_reason,
                        ],
                        'player'
                    );
                    
                    // Notify owner
                    if ($booking->owner && $booking->owner->phone) {
                        $whatsappService->sendCancellationToOwner(
                            $booking->owner->phone,
                            [
                                'booking_number' => $booking->booking_number,
                                'player_name' => $booking->player_name,
                                'booking_date' => $booking->booking_date->format('d M Y'),
                                'start_time' => $booking->start_time,
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('WhatsApp cancellation notification failed: ' . $e->getMessage());
            }
            
            return response()->json(['message' => 'Booking cancelled successfully']);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }
}
