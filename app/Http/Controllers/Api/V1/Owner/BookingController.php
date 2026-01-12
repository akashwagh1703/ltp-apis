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
    }

    public function createOffline(Request $request)
    {
        \Log::info('Offline booking request received', ['data' => $request->all()]);
        
        try {
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
                'payment_type' => 'required|in:full,partial,pay_on_turf',
                'paid_amount' => 'nullable|numeric|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Offline booking validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Offline booking error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }

        // Get first slot for primary booking
        try {
            $firstSlot = TurfSlot::findOrFail($request->slot_ids[0]);
        } catch (\Exception $e) {
            \Log::error('First slot not found', ['slot_id' => $request->slot_ids[0]]);
            return response()->json(['message' => 'Slot not found'], 404);
        }
        
        // Check all slots are available
        $slots = TurfSlot::whereIn('id', $request->slot_ids)->get();
        
        if ($slots->count() !== count($request->slot_ids)) {
            \Log::error('Some slots not found', [
                'requested' => $request->slot_ids,
                'found' => $slots->pluck('id')->toArray()
            ]);
            return response()->json(['message' => 'Some slots were not found'], 400);
        }
        
        foreach ($slots as $slot) {
            if ($slot->status !== 'available') {
                \Log::error('Slot not available', [
                    'slot_id' => $slot->id,
                    'status' => $slot->status
                ]);
                return response()->json([
                    'message' => "Slot {$slot->start_time} is already {$slot->status}"
                ], 400);
            }
        }

        // Calculate duration
        $startTime = \Carbon\Carbon::parse($request->start_time);
        $endTime = \Carbon\Carbon::parse($request->end_time);
        $duration = $startTime->diffInMinutes($endTime);

        // Calculate payment amounts based on payment type
        $paidAmount = 0;
        $pendingAmount = $request->amount;
        $paymentStatus = 'pending';
        $advancePercentage = null;

        if ($request->payment_type === 'full') {
            $paidAmount = $request->amount;
            $pendingAmount = 0;
            $paymentStatus = 'success';
        } elseif ($request->payment_type === 'partial') {
            $paidAmount = $request->paid_amount ?? 0;
            $pendingAmount = $request->amount - $paidAmount;
            $paymentStatus = 'partial';
            $advancePercentage = ($paidAmount / $request->amount) * 100;
        }
        // else pay_on_turf: paidAmount = 0, pendingAmount = full amount, status = pending

        // Get owner's commission rate
        $owner = $request->user();
        $commissionRate = $owner->commission_rate ?? 5.00;
        $platformCommission = ($request->amount * $commissionRate) / 100;
        $ownerPayout = $request->amount - $platformCommission;

        // Create booking for first slot
        try {
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
            
            \Log::info('Booking created successfully', ['booking_id' => $booking->id]);
        } catch (\Exception $e) {
            \Log::error('Booking creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create booking: ' . $e->getMessage()
            ], 500);
        }

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
            \Log::warning('WhatsApp offline booking notification failed: ' . $e->getMessage());
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
                    $this->notificationService->send(
                        $booking->player_id,
                        'player',
                        'Booking Cancelled',
                        "Your booking #{$booking->booking_number} has been cancelled by the owner. Reason: {$booking->cancellation_reason}"
                    );
                } catch (\Exception $e) {
                    \Log::error('Notification failed: ' . $e->getMessage());
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
        $booking = Booking::where('owner_id', auth()->id())->findOrFail($id);

        if ($booking->payment_status === 'success') {
            return response()->json(['message' => 'Payment already confirmed'], 400);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        // If partial payment, collect remaining amount
        if ($booking->payment_status === 'partial') {
            $additionalAmount = $validated['amount'] ?? $booking->pending_amount;
            $booking->paid_amount += $additionalAmount;
            $booking->pending_amount -= $additionalAmount;
            
            if ($booking->pending_amount <= 0) {
                $booking->payment_status = 'success';
                $booking->pending_amount = 0;
            }
        } else {
            // Full payment confirmation for pay_on_turf
            $booking->paid_amount = $booking->final_amount;
            $booking->pending_amount = 0;
            $booking->payment_status = 'success';
        }

        $booking->save();

        return response()->json([
            'message' => 'Payment confirmed successfully',
            'booking' => new BookingResource($booking->load('turf'))
        ]);
    }
}
