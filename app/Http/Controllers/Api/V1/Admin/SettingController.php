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

        Setting::set('platform_commission_rate', $request->rate, 'decimal', 'Platform commission percentage');

        return response()->json([
            'message' => 'Commission rate updated successfully',
            'commission_rate' => $request->rate
        ]);
    }
}
