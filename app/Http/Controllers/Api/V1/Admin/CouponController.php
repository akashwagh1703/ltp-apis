<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        try {
            $coupons = Coupon::latest()->get();
            return response()->json([
                'success' => true,
                'data' => $coupons
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coupons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:coupons,code|regex:/^[A-Z0-9]+$/',
                'description' => 'nullable|string|max:500',
                'discount_type' => 'required|in:percentage,fixed',
                'discount_value' => 'required|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'min_booking_amount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'valid_from' => 'required|date',
                'valid_until' => 'required|date|after:valid_from',
                'is_active' => 'nullable|boolean',
            ]);

            // Validate percentage discount
            if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage discount cannot exceed 100%'
                ], 422);
            }

            $validated['is_active'] = $validated['is_active'] ?? true;
            $coupon = Coupon::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully',
                'data' => $coupon
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'code' => 'sometimes|required|string|max:50|regex:/^[A-Z0-9]+$/|unique:coupons,code,' . $id,
                'description' => 'nullable|string|max:500',
                'discount_type' => 'sometimes|required|in:percentage,fixed',
                'discount_value' => 'sometimes|required|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'min_booking_amount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'valid_from' => 'sometimes|required|date',
                'valid_until' => 'sometimes|required|date|after:valid_from',
                'is_active' => 'nullable|boolean',
            ]);

            $coupon = Coupon::findOrFail($id);

            // Validate percentage discount
            if (isset($validated['discount_type']) && $validated['discount_type'] === 'percentage' && 
                isset($validated['discount_value']) && $validated['discount_value'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage discount cannot exceed 100%'
                ], 422);
            }

            $coupon->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully',
                'data' => $coupon
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);
            
            // Check if coupon has been used
            if ($coupon->used_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete coupon that has been used. Consider deactivating it instead.'
                ], 400);
            }

            $coupon->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Coupon deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
