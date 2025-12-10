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

        $otp = $this->otpService->generate($request->phone, 'login');
        $this->smsService->sendOtp($request->phone, $otp);

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:15',
            'otp' => 'required|string|size:6',
        ]);

        if (!$this->otpService->verify($request->phone, $request->otp, 'login')) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $owner = Owner::where('phone', $request->phone)->first();

        if (!$owner || $owner->status !== 'active') {
            return response()->json(['message' => 'Account not active'], 403);
        }

        $token = $owner->createToken('owner-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'owner' => $owner,
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
        $owner->update($request->only(['name', 'email', 'profile_image']));
        return response()->json($owner);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
