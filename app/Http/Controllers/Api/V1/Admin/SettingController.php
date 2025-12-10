<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::all());
    }

    public function update(Request $request)
    {
        foreach ($request->settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function updateSingle(Request $request, $key)
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $request->value]
        );

        return response()->json(['message' => 'Setting updated successfully', 'data' => $setting]);
    }
}
