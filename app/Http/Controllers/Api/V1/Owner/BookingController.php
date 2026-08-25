<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TurfSlot;
use App\Services\FcmService;
use App\Services\SmsService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $smsService;
    protected $fcmService;

    public function __construct(SmsService $smsService, FcmService $fcmService)
    {
        $this->smsService = $smsService;
        $this->fcmService = $fcmService;
    }

    public function index(Request $request)
    {
        try {
            $query = Booking::with(['turf', 'player', 'payment'])
                ->where('owner_id', $request->user()->id);

            if ($request->status === 'needs_confirmation') {
                $query->whereIn('booking_status', [
                    Booking::STATUS_AWAITING_CONFIRMATION,
                    Booking::STATUS_PAY_ON_ARRIVAL,
                ]);
            } elseif ($request->status) {
                $query->where('booking_status', $request->status);
            }

            if ($request->booking_type) {
                $query->where('booking_type', $request->booking_type);
            }

            if ($request->payment_status) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->turf_id) {
                $query->where('turf_id', $request->turf_id);
            }

            if ($request->date) {
                $query->whereDate('booking_date', $request->date);
            }

            $bookings = $query->latest()->paginate(15);

            return BookingResource::collection($bookings);
        } catch (\Exception $e) {
            \Log::error('Booking listing failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'message' => 'Failed to fetch bookings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createOffline(Request $request)
    {
        try {
            \Log::info('Offline booking request', $request->all());
            
            $validated = $request->validate([
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
                'payment_type' => 'nullable|in:full,partial,pay_on_turf',
                'paid_amount' => 'nullable|numeric|min:0',
            ]);
            
            \Log::info('Validation passed');
            
            // Get first slot
            $firstSlot = TurfSlot::findOrFail($request->slot_ids[0]);
            
            // Check all slots are available
            $slots = TurfSlot::whereIn('id', $request->slot_ids)->get();
            
            if ($slots->count() !== count($request->slot_ids)) {
                return response()->json(['message' => 'Some slots were not found'], 400);
            }
            
            foreach ($slots as $slot) {
                if ($slot->status !== 'available') {
                    return response()->json(['message' => "Slot {$slot->start_time} is already {$slot->status}"], 400);
                }
            }
            
            // Calculate duration
            $startTime = \Carbon\Carbon::parse($request->start_time);
            $endTime = \Carbon\Carbon::parse($request->end_time);
            $duration = $startTime->diffInMinutes($endTime);
            
            // Calculate payment amounts
            $paymentType = $request->payment_type ?? 'full';
            $paidAmount = 0;
            $pendingAmount = $request->amount;
            $paymentStatus = 'pending';
            $advancePercentage = null;
            
            if ($paymentType === 'full') {
                $paidAmount = $request->amount;
                $pendingAmount = 0;
                $paymentStatus = 'success';
            } elseif ($paymentType === 'partial') {
                $paidAmount = $request->paid_amount ?? 0;
                $pendingAmount = $request->amount - $paidAmount;
                $paymentStatus = $paidAmount > 0 ? 'pending' : 'pending'; // Use 'pending' until migration runs
                if ($paidAmount > 0) {
                    $advancePercentage = ($paidAmount / $request->amount) * 100;
                }
            }
            
            \Log::info('Payment calculated', [
                'paid' => $paidAmount,
                'pending' => $pendingAmount,
                'status' => $paymentStatus
            ]);
            
            // Get owner's commission rate
            $owner = $request->user();
            $commissionRate = $owner->commission_rate ?? 5.00;
            $platformCommission = ($request->amount * $commissionRate) / 100;
            $ownerPayout = $request->amount - $platformCommission;
            
            // Create booking
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
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'advance_percentage' => $advancePercentage,
                'platform_commission' => $platformCommission,
                'owner_payout' => $ownerPayout,
                'commission_rate' => $commissionRate,
                'booking_type' => 'offline',
                'booking_status' => 'confirmed',
                'payment_mode' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'player_name' => $request->player_name,
                'player_phone' => $request->player_phone,
            ]);
            
            \Log::info('Booking created', ['id' => $booking->id]);
            
            // Mark all slots as booked
            foreach ($slots as $slot) {
                $slot->update(['status' => 'booked_offline']);
            }
            
            // Send WhatsApp notification (non-blocking)
            try {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappService->sendBookingConfirmation(
                    $request->player_phone,
                    [
                        'booking_number' => $booking->booking_number,
                        'turf_name' => $booking->turf->name,
                        'booking_date' => $booking->booking_date->format('Y-m-d'),
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'final_amount' => $booking->final_amount,
                        'payment_mode' => ucfirst($request->payment_method),
                    ],
                    false
                );
            } catch (\Exception $e) {
                \Log::warning('WhatsApp notification failed: ' . $e->getMessage());
            }
            
            return response()->json(new BookingResource($booking->load('turf')), 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Booking creation failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'message' => 'Booking failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function stats(Request $request)
    {
        $ownerId = $request->user()->id;

        return response()->json([
            'total_bookings' => Booking::where('owner_id', $ownerId)->count(),
            'today_bookings' => Booking::where('owner_id', $ownerId)->whereDate('booking_date', today())->count(),
            'total_revenue' => Booking::where('owner_id', $ownerId)->where('booking_status', 'completed')->sum('final_amount'),
            'pending_bookings' => Booking::where('owner_id', $ownerId)->where('payment_status', 'pending')->count(),
            'awaiting_confirmation' => Booking::where('owner_id', $ownerId)
                ->whereIn('booking_status', [
                    Booking::STATUS_AWAITING_CONFIRMATION,
                    Booking::STATUS_PAY_ON_ARRIVAL,
                ])
                ->count(),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        try {
            \DB::beginTransaction();

            $booking = Booking::where('owner_id', auth()->id())->findOrFail($id);

            if ($booking->booking_status === 'cancelled') {
                return response()->json(['message' => 'Booking already cancelled'], 400);
            }

            if ($booking->booking_status === 'completed') {
                return response()->json(['message' => 'Cannot cancel completed booking'], 400);
            }

            $booking->update([
                'booking_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'owner',
                'cancellation_reason' => $request->reason ?? 'Cancelled by owner'
            ]);

            // Release all slots in the booking time range
            TurfSlot::where('turf_id', $booking->turf_id)
                ->where('date', $booking->booking_date)
                ->where(function($q) use ($booking) {
                    $q->whereBetween('start_time', [$booking->start_time, $booking->end_time])
                      ->orWhereBetween('end_time', [$booking->start_time, $booking->end_time]);
                })
                ->whereIn('status', ['booked_online', 'booked_offline'])
                ->update(['status' => 'available']);

            // Notify player if online booking
            if ($booking->booking_type === 'online' && $booking->player_id) {
                try {
                    $this->fcmService->sendCancellationNotification($booking, false);
                } catch (\Exception $e) {
                    \Log::error('FCM notification failed: ' . $e->getMessage());
                }
            }

            // Send WhatsApp notification (non-blocking)
            try {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                
                if ($booking->player_phone) {
                    $whatsappService->sendCancellationToPlayer(
                        $booking->player_phone,
                        [
                            'booking_number' => $booking->booking_number,
                            'turf_name' => $booking->turf->name,
                            'booking_date' => $booking->booking_date->format('d M Y'),
                            'start_time' => $booking->start_time,
                            'cancellation_reason' => $booking->cancellation_reason,
                        ],
                        'owner'
                    );
                }
            } catch (\Exception $e) {
                \Log::warning('WhatsApp owner cancellation notification failed: ' . $e->getMessage());
            }

            \DB::commit();
            return response()->json(['message' => 'Booking cancelled successfully']);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }

    public function complete($id)
    {
        $booking = Booking::where('owner_id', auth()->id())->findOrFail($id);

        if ($booking->booking_status === 'completed') {
            return response()->json(['message' => 'Booking already completed'], 400);
        }

        if ($booking->booking_status === 'cancelled') {
            return response()->json(['message' => 'Cannot complete cancelled booking'], 400);
        }

        $booking->update(['booking_status' => 'completed']);

        // Send WhatsApp notification (non-blocking)
        try {
            if ($booking->player_phone) {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappService->sendBookingCompleted(
                    $booking->player_phone,
                    [
                        'booking_number' => $booking->booking_number,
                        'turf_name' => $booking->turf->name,
                        'booking_date' => $booking->booking_date->format('d M Y'),
                        'start_time' => $booking->start_time,
                    ]
                );
            }
        } catch (\Exception $e) {
            \Log::warning('WhatsApp booking completion notification failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Booking marked as completed']);
    }

    public function markNoShow($id)
    {
        $booking = Booking::where('owner_id', auth()->id())->findOrFail($id);

        if ($booking->booking_status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed bookings can be marked as no-show'], 400);
        }

        $booking->update(['booking_status' => 'no_show']);

        return response()->json(['message' => 'Booking marked as no-show']);
    }

    public function confirmPayment(Request $request, $id)
    {
        $booking = Booking::with(['turf', 'player'])
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        if ($booking->booking_status === Booking::STATUS_EXPIRED) {
            return response()->json(['message' => 'This booking has expired'], 400);
        }

        if ($booking->needsOwnerPaymentConfirm()) {
            if ($booking->isAdvancePay()) {
                $due = $booking->dueNowAmount();
                $rest = round(max(0, (float) $booking->final_amount - $due), 2);
                $booking->update([
                    'booking_status' => Booking::STATUS_CONFIRMED,
                    'payment_status' => 'partial',
                    'paid_amount' => $due,
                    'pending_amount' => $rest,
                ]);
            } else {
                $booking->update([
                    'booking_status' => Booking::STATUS_CONFIRMED,
                    'payment_status' => 'success',
                    'paid_amount' => $booking->final_amount,
                    'pending_amount' => 0,
                ]);
            }

            $booking->refresh();
            $booking->load(['turf', 'player', 'owner']);

            try {
                $this->fcmService->sendBookingNotification($booking, false);
            } catch (\Exception $e) {
                \Log::warning('FCM confirm notification failed: ' . $e->getMessage());
            }

            try {
                if ($booking->player_phone && class_exists('\App\Services\WhatsAppService')) {
                    $whatsappService = app(\App\Services\WhatsAppService::class);
                    $whatsappService->sendBookingConfirmation(
                        $booking->player_phone,
                        [
                            'booking_number' => $booking->booking_number,
                            'turf_name' => $booking->turf->name ?? '',
                            'booking_date' => $booking->booking_date->format('d M Y'),
                            'start_time' => $booking->start_time,
                            'end_time' => $booking->end_time,
                            'final_amount' => $booking->final_amount,
                        ],
                        true
                    );
                }
            } catch (\Exception $e) {
                \Log::warning('WhatsApp confirm notification failed: ' . $e->getMessage());
            }

            $amount = $booking->isAdvancePay()
                ? $booking->dueNowAmount()
                : (float) $booking->final_amount;
            $remaining = (float) $booking->pending_amount;
            $message = $remaining > 0
                ? "Received ₹{$amount} advance from {$booking->player_name}. ₹{$remaining} due at the turf."
                : "Received ₹{$amount} from {$booking->player_name}";

            return response()->json([
                'success' => true,
                'message' => $message,
                'payment_details' => [
                    'previous_paid_amount' => 0,
                    'additional_amount_paid' => $amount,
                    'total_paid_amount' => (float) $booking->paid_amount,
                    'remaining_amount' => $remaining,
                    'total_booking_amount' => (float) $booking->final_amount,
                    'payment_status' => $booking->payment_status,
                ],
                'booking' => new BookingResource($booking),
            ]);
        }

        if ($booking->payment_status === 'success') {
            return response()->json(['message' => 'Payment already confirmed'], 400);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        $previousPaidAmount = $booking->paid_amount;
        $previousPendingAmount = $booking->pending_amount;
        $additionalAmount = 0;

        // If partial payment, collect remaining amount
        if ($booking->payment_status === 'pending' && $booking->pending_amount > 0) {
            $additionalAmount = $validated['amount'] ?? $booking->pending_amount;
            $booking->paid_amount += $additionalAmount;
            $booking->pending_amount -= $additionalAmount;

            if ($booking->pending_amount <= 0) {
                $booking->payment_status = 'success';
                $booking->pending_amount = 0;
            }
        } else {
            // Full payment confirmation for pay_on_turf
            $additionalAmount = $booking->final_amount;
            $booking->paid_amount = $booking->final_amount;
            $booking->pending_amount = 0;
            $booking->payment_status = 'success';
        }

        $booking->save();

        return response()->json([
            'message' => 'Payment confirmed successfully',
            'payment_details' => [
                'previous_paid_amount' => (float) $previousPaidAmount,
                'additional_amount_paid' => (float) $additionalAmount,
                'total_paid_amount' => (float) $booking->paid_amount,
                'remaining_amount' => (float) $booking->pending_amount,
                'total_booking_amount' => (float) $booking->final_amount,
                'payment_status' => $booking->payment_status,
            ],
            'booking' => new BookingResource($booking->load('turf'))
        ]);
    }

    public function rejectPayment(Request $request, $id)
    {
        $booking = Booking::with(['turf', 'player'])
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        if (!$booking->needsOwnerPaymentConfirm()) {
            return response()->json(['message' => 'This booking is not waiting for payment confirmation'], 400);
        }

        $reason = $request->input('reason');

        try {
            if ($booking->player_id) {
                $this->fcmService->sendToUserAsync(
                    $booking->player_id,
                    'player',
                    'Payment not received',
                    $reason
                        ? "{$booking->turf->name} has not received ₹{$booking->final_amount}. {$reason}"
                        : "{$booking->turf->name} has not received ₹{$booking->final_amount} yet. Pay again and tap I have paid.",
                    [
                        'type' => 'payment',
                        'booking_id' => (string) $booking->id,
                    ],
                    'payment'
                );
            }
        } catch (\Exception $e) {
            \Log::warning('FCM reject-payment notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Told the player you have not received payment yet',
            'booking' => new BookingResource($booking),
        ]);
    }
}
