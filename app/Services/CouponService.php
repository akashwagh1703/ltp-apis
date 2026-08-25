<?php

namespace App\Services;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function findUsable(string $code): Coupon
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', Carbon::today())
            ->whereDate('valid_to', '>=', Carbon::today())
            ->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This code is not valid or has expired.',
            ]);
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This code has been used up.',
            ]);
        }

        return $coupon;
    }

    public function discountFor(Coupon $coupon, float $amount): float
    {
        if ($amount < (float) $coupon->min_booking_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Minimum booking amount is ₹'.number_format((float) $coupon->min_booking_amount, 0).'.',
            ]);
        }

        if ($coupon->discount_type === 'percentage') {
            $discount = ($amount * (float) $coupon->discount_value) / 100;
            if ($coupon->max_discount && $discount > (float) $coupon->max_discount) {
                $discount = (float) $coupon->max_discount;
            }
        } else {
            $discount = (float) $coupon->discount_value;
        }

        return round(min($discount, $amount), 2);
    }
}
