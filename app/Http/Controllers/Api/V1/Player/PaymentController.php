<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    protected $razorpay;

    public function __construct()
    {
        $this->razorpay = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        try {
            $booking = Booking::findOrFail($request->booking_id);

            // Create Razorpay order
            $order = $this->razorpay->order->create([
                'amount' => $booking->final_amount * 100, // Amount in paise
                'currency' => 'INR',
                'receipt' => 'booking_' . $booking->id,
                'notes' => [
                    'booking_id' => $booking->id,
                    'player_id' => $booking->player_id,
                    'turf_id' => $booking->turf_id,
                ]
            ]);

            // Store payment record
            Payment::create([
                'booking_id' => $booking->id,
                'player_id' => $booking->player_id,
                'amount' => $booking->final_amount,
                'payment_method' => 'razorpay',
                'payment_status' => 'pending',
                'razorpay_order_id' => $order['id'],
            ]);

            return response()->json([
                'order_id' => $order['id'],
                'amount' => $booking->final_amount,
                'currency' => 'INR',
                'key' => config('services.razorpay.key'),
                'booking' => $booking
            ]);
        } catch (\Exception $e) {
            \Log::error('Razorpay order creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create payment order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
            'booking_id' => 'required|exists:bookings,id',
        ]);

        try {
            // Verify signature
            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $this->razorpay->utility->verifyPaymentSignature($attributes);

            // Update payment record
            $payment = Payment::where('razorpay_order_id', $request->razorpay_order_id)->first();
            if ($payment) {
                $payment->update([
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature' => $request->razorpay_signature,
                    'payment_status' => 'success',
                    'paid_at' => now(),
                ]);
            }

            // Update booking
            $booking = Booking::findOrFail($request->booking_id);
            $booking->update([
                'payment_status' => 'success',
                'payment_mode' => 'online',
            ]);

            // Send notification to owner
            $this->sendOwnerNotification($booking);

            return response()->json([
                'message' => 'Payment verified successfully',
                'booking' => $booking->load('turf', 'payment')
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment verification failed: ' . $e->getMessage());
            
            // Update payment as failed
            $payment = Payment::where('razorpay_order_id', $request->razorpay_order_id)->first();
            if ($payment) {
                $payment->update(['payment_status' => 'failed']);
            }

            return response()->json([
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    protected function sendOwnerNotification($booking)
    {
        try {
            // Create notification for owner
            \App\Models\Notification::create([
                'user_id' => $booking->owner_id,
                'user_type' => 'owner',
                'type' => 'new_booking',
                'title' => 'New Booking Received',
                'message' => "New booking from {$booking->player_name} for {$booking->turf->name} on {$booking->booking_date}",
                'data' => json_encode([
                    'booking_id' => $booking->id,
                    'player_name' => $booking->player_name,
                    'turf_name' => $booking->turf->name,
                    'booking_date' => $booking->booking_date,
                    'amount' => $booking->final_amount,
                ]),
                'is_read' => false,
            ]);

            \Log::info('Owner notification sent for booking: ' . $booking->id);
        } catch (\Exception $e) {
            \Log::error('Failed to send owner notification: ' . $e->getMessage());
        }
    }
}
