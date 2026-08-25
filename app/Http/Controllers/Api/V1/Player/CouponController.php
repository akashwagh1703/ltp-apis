<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function validate(Request $request, CouponService $coupons)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        try {
            $coupon = $coupons->findUsable($request->code);
            $discount = $coupons->discountFor($coupon, (float) $request->amount);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Invalid coupon',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'discount' => $discount,
            'final_amount' => round((float) $request->amount - $discount, 2),
            'coupon' => [
                'code' => $coupon->code,
                'description' => $coupon->description,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
            ],
        ]);
    }

    public function available()
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', now())
            ->whereDate('valid_to', '>=', now())
            ->get(['code', 'description', 'discount_type', 'discount_value', 'min_booking_amount', 'max_discount', 'valid_to']);

        return response()->json([
            'success' => true,
            'data' => $coupons,
            'booking_advance_percent' => \App\Models\Setting::getBookingAdvancePercent(),
        ]);
    }
}
