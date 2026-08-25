<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->keyBy('key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($request->settings as $setting) {
            Setting::set($setting['key'], $setting['value']);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function updateSingle(Request $request, $key)
    {
        $request->validate(['value' => 'required']);
        
        Setting::set($key, $request->value);
        
        return response()->json(['message' => 'Setting updated successfully']);
    }

    public function getCommissionRate()
    {
        return response()->json([
            'commission_rate' => Setting::getCommissionRate()
        ]);
    }

    public function updateCommissionRate(Request $request)
    {
        $request->validate([
            'rate' => 'required|numeric|min:0|max:100'
        ]);

        Setting::set('platform_commission_rate', $request->rate, 'decimal');

        return response()->json([
            'message' => 'Commission rate updated successfully',
            'commission_rate' => $request->rate
        ]);
    }

    public function getSmsSettings()
    {
        try {
            return response()->json([
                'sms_enabled' => Setting::get('sms_enabled', 'false') === 'true',
                'default_otp_enabled' => Setting::isDefaultOtpEnabled(),
                'default_otp' => Setting::get('default_otp', '999999'),
                'msg91_auth_key' => Setting::get('msg91_auth_key', ''),
                'msg91_sender_id' => Setting::get('msg91_sender_id', 'LTPLAY'),
                'msg91_otp_template_id' => Setting::get('msg91_otp_template_id', ''),
                'msg91_booking_template_id' => Setting::get('msg91_booking_template_id', ''),
                'msg91_cancel_template_id' => Setting::get('msg91_cancel_template_id', ''),
                'msg91_dlt_entity_id' => Setting::get('msg91_dlt_entity_id', ''),
            ]);
        } catch (\Exception $e) {
            // Return default values if settings don't exist yet
            return response()->json([
                'sms_enabled' => false,
                'default_otp_enabled' => false,
                'default_otp' => '999999',
                'msg91_auth_key' => '',
                'msg91_sender_id' => 'LTPLAY',
                'msg91_otp_template_id' => '',
                'msg91_booking_template_id' => '',
                'msg91_cancel_template_id' => '',
                'msg91_dlt_entity_id' => '',
            ]);
        }
    }

    public function updateSmsSettings(Request $request)
    {
        $request->validate([
            'sms_enabled' => 'required|boolean',
            'default_otp_enabled' => 'required|boolean',
            'default_otp' => 'required|string|size:6',
            'msg91_auth_key' => 'nullable|string',
            'msg91_sender_id' => 'nullable|string|max:6',
            'msg91_otp_template_id' => 'nullable|string',
            'msg91_booking_template_id' => 'nullable|string',
            'msg91_cancel_template_id' => 'nullable|string',
            'msg91_dlt_entity_id' => 'nullable|string',
        ]);

        try {
            $settings = [
                'sms_enabled' => $request->sms_enabled ? 'true' : 'false',
                'default_otp_enabled' => $request->default_otp_enabled ? 'true' : 'false',
                'default_otp' => $request->default_otp,
                'msg91_auth_key' => $request->msg91_auth_key ?? '',
                'msg91_sender_id' => $request->msg91_sender_id ?? 'LTPLAY',
                'msg91_otp_template_id' => $request->msg91_otp_template_id ?? '',
                'msg91_booking_template_id' => $request->msg91_booking_template_id ?? '',
                'msg91_cancel_template_id' => $request->msg91_cancel_template_id ?? '',
                'msg91_dlt_entity_id' => $request->msg91_dlt_entity_id ?? '',
            ];

            foreach ($settings as $key => $value) {
                Setting::set($key, $value, 'text');
            }

            return response()->json([
                'message' => 'SMS settings updated successfully',
                'settings' => $this->getSmsSettings()->getData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update SMS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentSettings()
    {
        try {
            return response()->json([
                'razorpay_enabled' => Setting::get('razorpay_enabled', 'false') === 'true',
                'razorpay_mode' => Setting::get('razorpay_mode', 'test'),
                'razorpay_key_id' => Setting::get('razorpay_key_id', ''),
                'razorpay_key_secret' => Setting::get('razorpay_key_secret', ''),
                'razorpay_webhook_secret' => Setting::get('razorpay_webhook_secret', ''),
                'razorpay_payouts_enabled' => Setting::get('razorpay_payouts_enabled', 'false') === 'true',
                'razorpay_payout_key_id' => Setting::get('razorpay_payout_key_id', ''),
                'razorpay_payout_key_secret' => Setting::get('razorpay_payout_key_secret', ''),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'razorpay_enabled' => false,
                'razorpay_mode' => 'test',
                'razorpay_key_id' => '',
                'razorpay_key_secret' => '',
                'razorpay_webhook_secret' => '',
                'razorpay_payouts_enabled' => false,
                'razorpay_payout_key_id' => '',
                'razorpay_payout_key_secret' => '',
            ]);
        }
    }

    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'razorpay_enabled' => 'required|boolean',
            'razorpay_mode' => 'required|in:test,live',
            'razorpay_key_id' => 'nullable|string',
            'razorpay_key_secret' => 'nullable|string',
            'razorpay_webhook_secret' => 'nullable|string',
            'razorpay_payouts_enabled' => 'boolean',
            'razorpay_payout_key_id' => 'nullable|string',
            'razorpay_payout_key_secret' => 'nullable|string',
        ]);

        try {
            $settings = [
                'razorpay_enabled' => $request->razorpay_enabled ? 'true' : 'false',
                'razorpay_mode' => $request->razorpay_mode,
                'razorpay_key_id' => $request->razorpay_key_id ?? '',
                'razorpay_key_secret' => $request->razorpay_key_secret ?? '',
                'razorpay_webhook_secret' => $request->razorpay_webhook_secret ?? '',
                'razorpay_payouts_enabled' => $request->razorpay_payouts_enabled ? 'true' : 'false',
                'razorpay_payout_key_id' => $request->razorpay_payout_key_id ?? '',
                'razorpay_payout_key_secret' => $request->razorpay_payout_key_secret ?? '',
            ];

            foreach ($settings as $key => $value) {
                Setting::set($key, $value, 'text');
            }

            return response()->json([
                'message' => 'Payment settings updated successfully',
                'settings' => $this->getPaymentSettings()->getData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update payment settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPlatformUpi()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'upi_id' => Setting::platformUpiId(),
                'qr_url' => Setting::platformQrUrl(),
                'qr_path' => Setting::get('platform_qr_path', ''),
            ],
        ]);
    }

    public function updatePlatformUpi(Request $request)
    {
        $validated = $request->validate([
            'upi_id' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]{2,256}@[a-zA-Z0-9.-]{2,64}$/'],
            'qr' => 'sometimes|file|max:12288',
        ]);

        if ($request->filled('upi_id')) {
            Setting::set('platform_upi_id', strtolower(trim($request->input('upi_id'))), 'text');
        }

        if ($request->hasFile('qr')) {
            try {
                $media = app(\App\Services\MediaService::class);
                $old = Setting::get('platform_qr_path', '');
                $media->delete($old);
                $path = $media->putUploadedFile($request->file('qr'), $media->platformQrStem(), false);
                Setting::set('platform_qr_path', $path, 'text');
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform UPI saved',
            'data' => [
                'upi_id' => Setting::platformUpiId(),
                'qr_url' => Setting::platformQrUrl(),
            ],
        ]);
    }
}
