<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Payment;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $razorpay;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = Setting::get('razorpay_enabled', 'false') === 'true';
        
        if ($this->enabled) {
            $keyId = Setting::get('razorpay_key_id');
            $keySecret = Setting::get('razorpay_key_secret');
            
            if ($keyId && $keySecret) {
                $this->razorpay = new Api($keyId, $keySecret);
            }
        }
    }

    public function createOrder($bookingId, $amount, $currency = 'INR')
    {
        if (!$this->enabled || !$this->razorpay) {
            throw new \Exception('Razorpay not configured or disabled');
        }

        try {
            $order = $this->razorpay->order->create([
                'amount' => $amount * 100, // Convert to paise
                'currency' => $currency,
                'receipt' => 'booking_' . $bookingId,
                'notes' => [
                    'booking_id' => $bookingId
                ]
            ]);

            return [
                'order_id' => $order['id'],
                'amount' => $amount,
                'currency' => $currency,
                'key' => Setting::get('razorpay_key_id')
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verifyPayment($orderId, $paymentId, $signature)
    {
        if (!$this->enabled || !$this->razorpay) {
            return false;
        }

        try {
            $attributes = [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature
            ];

            $this->razorpay->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (\Exception $e) {
            Log::error('Razorpay payment verification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function recordPayment($bookingId, $orderId, $paymentId, $amount, $status = 'success')
    {
        return Payment::create([
            'booking_id' => $bookingId,
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'amount' => $amount,
            'payment_method' => 'razorpay',
            'payment_status' => $status,
            'paid_at' => $status === 'success' ? now() : null,
        ]);
    }
}
