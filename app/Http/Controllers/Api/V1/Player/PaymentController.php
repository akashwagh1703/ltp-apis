<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function createOrder(Request $request)
    {
        return $this->gone();
    }

    public function verifyPayment(Request $request)
    {
        return $this->gone();
    }

    protected function gone()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'GONE',
                'message' => 'Use UPI QR payment. Scan the owner QR, then tap I have paid.',
            ],
        ], 410);
    }
}
