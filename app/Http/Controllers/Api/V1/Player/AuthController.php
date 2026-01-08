<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Player;
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
        try {
            $request->validate(['phone' => 'required|string|max:15']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $key = 'otp_attempts:' . $request->phone;
        $attempts = \Cache::get($key, 0);
        
        if ($attempts >= 3) {
            return response()->json(['message' => 'Too many OTP requests. Please try after 10 minutes.'], 429)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $otp = $this->otpService->generate($request->phone, 'login');
        $this->smsService->sendOtp($request->phone, $otp);

        \Cache::put($key, $attempts + 1, now()->addMinutes(10));

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'phone' => 'required|string|max:15',
                'otp' => 'required|string|size:6',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422)
                ->header('Access-Control-Allow-Origin', '*');
        }

        if (!$this->otpService->verify($request->phone, $request->otp, 'login')) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $player = Player::firstOrCreate(
            ['phone' => $request->phone],
            ['status' => 'active']
        );

        $token = $player->createToken('player-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'player' => $player,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'profile_image' => 'nullable|string',
        ]);

        $player = $request->user();
        $player->update($request->only(['name', 'email', 'profile_image']));
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'player' => $player,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
