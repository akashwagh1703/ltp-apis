<?php

namespace App\Services;

use App\Models\Payment;

class PaymentService
{
    public function createOrder($bookingId, $amount)
    {
        $gateway = config('services.payment.gateway');

        if ($gateway === 'razorpay') {
            return $this->createRazorpayOrder($bookingId, $amount);
        } elseif ($gateway === 'cashfree') {
            return $this->createCashfreeOrder($bookingId, $amount);
        }

        return null;
    }

    private function createRazorpayOrder($bookingId, $amount)
    {
        // Razorpay order creation
        return [
            'order_id' => 'order_' . uniqid(),
            'amount' => $amount * 100,
            'currency' => 'INR',
        ];
    }

    private function createCashfreeOrder($bookingId, $amount)
    {
        // Cashfree order creation
        return [
            'order_id' => 'order_' . uniqid(),
            'amount' => $amount,
            'currency' => 'INR',
        ];
    }

    public function verifyPayment($orderId, $paymentId, $signature)
    {
        $gateway = config('services.payment.gateway');

        if ($gateway === 'razorpay') {
            return $this->verifyRazorpay($orderId, $paymentId, $signature);
        } elseif ($gateway === 'cashfree') {
            return $this->verifyCashfree($orderId, $paymentId, $signature);
        }

        return false;
    }

    private function verifyRazorpay($orderId, $paymentId, $signature)
    {
        // Razorpay signature verification
        return true; // Placeholder
    }

    private function verifyCashfree($orderId, $paymentId, $signature)
    {
        // Cashfree signature verification
        return true; // Placeholder
    }

    public function recordPayment($bookingId, $transactionId, $amount, $method, $gateway)
    {
        return Payment::create([
            'booking_id' => $bookingId,
            'transaction_id' => $transactionId,
            'payment_gateway' => $gateway,
            'payment_method' => $method,
            'amount' => $amount,
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }
}
