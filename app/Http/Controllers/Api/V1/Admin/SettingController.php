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
                'default_otp_enabled' => Setting::get('default_otp_enabled', 'true') === 'true',
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
                'default_otp_enabled' => true,
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
}
