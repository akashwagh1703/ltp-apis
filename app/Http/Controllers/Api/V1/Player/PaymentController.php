<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        try {
            $booking = Booking::findOrFail($request->booking_id);
            
            if ($booking->payment_status === 'success') {
                return response()->json(['message' => 'Booking already paid'], 400);
            }

            $orderData = $this->paymentService->createOrder(
                $booking->id,
                $booking->final_amount
            );

            return response()->json([
                'success' => true,
                'data' => $orderData,
                'booking' => $booking
            ]);
        } catch (\Exception $e) {
            Log::error('Payment order creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
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
            $verified = $this->paymentService->verifyPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed'
                ], 400);
            }

            $booking = Booking::findOrFail($request->booking_id);
            
            // Update booking
            $booking->update([
                'payment_status' => 'success',
                'payment_mode' => 'online',
            ]);

            // Record payment
            $this->paymentService->recordPayment(
                $booking->id,
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $booking->final_amount,
                'success'
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'booking' => $booking->load('turf')
            ]);
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            
            // Record failed payment
            $this->paymentService->recordPayment(
                $request->booking_id,
                $request->razorpay_order_id,
                $request->razorpay_payment_id ?? null,
                0,
                'failed'
            );

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
