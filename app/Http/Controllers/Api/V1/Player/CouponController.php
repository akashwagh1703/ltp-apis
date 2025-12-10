<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $coupon = Coupon::where('code', $request->code)
            ->where('is_active', true)
            ->where('valid_from', '<=', Carbon::now())
            ->where('valid_to', '>=', Carbon::now())
            ->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid or expired coupon'], 400);
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['message' => 'Coupon usage limit reached'], 400);
        }

        if ($request->amount < $coupon->min_booking_amount) {
            return response()->json(['message' => "Minimum booking amount is {$coupon->min_booking_amount}"], 400);
        }

        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = ($request->amount * $coupon->discount_value) / 100;
            if ($coupon->max_discount && $discount > $coupon->max_discount) {
                $discount = $coupon->max_discount;
            }
        } else {
            $discount = $coupon->discount_value;
        }

        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'final_amount' => $request->amount - $discount,
            'coupon' => $coupon,
        ]);
    }

    public function available()
    {
        $coupons = Coupon::where('is_active', true)
            ->where('valid_from', '<=', Carbon::now())
            ->where('valid_to', '>=', Carbon::now())
            ->get();

        return response()->json($coupons);
    }
}
