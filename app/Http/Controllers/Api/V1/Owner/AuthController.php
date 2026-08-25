<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $otpService;
    protected $smsService;

    public function __construct(OtpService $otpService, SmsService $smsService)
    {
        $this->otpService = $otpService;
        $this->smsService = $smsService;
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:15']);

        $owner = Owner::where('phone', $request->phone)->first();
        
        if (!$owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }

        try {
            $otp = $this->otpService->generate($request->phone, 'login');
        } catch (\RuntimeException $e) {
            $status = $e->getCode() === 429 ? 429 : 400;
            return response()->json(['message' => $e->getMessage()], $status);
        }
        
        // Try WhatsApp first, but don't block if it fails
        try {
            $whatsappService = app(\App\Services\WhatsAppService::class);
            $whatsappService->sendOtp($request->phone, $otp, 'owner');
        } catch (\Exception $e) {
            \Log::warning('WhatsApp OTP failed, continuing: ' . $e->getMessage());
        }
        
        // Fallback to SMS (currently logs only)
        try {
            $this->smsService->sendOtp($request->phone, $otp);
        } catch (\Exception $e) {
            \Log::warning('SMS OTP failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:15',
            'otp' => 'required|string|size:6',
        ]);

        try {
            $valid = $this->otpService->verify($request->phone, $request->otp, 'login');
        } catch (\RuntimeException $e) {
            $status = $e->getCode() === 429 ? 429 : 400;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $owner = Owner::where('phone', $request->phone)->first();

        if (!$owner || $owner->status !== 'active') {
            return response()->json(['message' => 'Account not active'], 403);
        }

        $token = $owner->createToken('owner-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'owner' => (new \App\Http\Resources\OwnerResource($owner))->resolve(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(Request $request)
    {
        $owner = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'profile_image' => 'sometimes|nullable|string|max:500',
            'upi_id' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]{2,256}@[a-zA-Z0-9.-]{2,64}$/'],
            'qr' => 'sometimes|file|max:12288',
        ]);

        $owner->fill($request->only(['name', 'email', 'profile_image']));

        if ($request->filled('upi_id')) {
            $owner->upi_id = strtolower(trim($request->input('upi_id')));
        }

        if ($request->hasFile('qr')) {
            try {
                $owner->upi_qr_path = $owner->storeUpiQr($request->file('qr'));
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $owner->save();

        return response()->json([
            'success' => true,
            'data' => (new \App\Http\Resources\OwnerResource($owner->fresh()))->resolve(),
            'message' => $owner->hasUpiSetup()
                ? 'UPI saved. Players can pay you by scanning this QR.'
                : 'Profile updated',
        ]);
    }

    public function me(Request $request)
    {
        $owner = $request->user();

        return response()->json([
            'success' => true,
            'data' => (new \App\Http\Resources\OwnerResource($owner))->resolve(),
        ]);
    }
}
