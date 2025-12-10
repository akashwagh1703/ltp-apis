<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['key' => 'commission_percentage', 'value' => '10', 'type' => 'number'],
            ['key' => 'slot_lock_minutes', 'value' => '10', 'type' => 'number'],
            ['key' => 'otp_expiry_minutes', 'value' => '10', 'type' => 'number'],
            ['key' => 'cancellation_hours', 'value' => '24', 'type' => 'number'],
            ['key' => 'app_name', 'value' => 'Let\'s Turf Play', 'type' => 'string'],
            ['key' => 'support_email', 'value' => 'support@letsturf.com', 'type' => 'string'],
            ['key' => 'support_phone', 'value' => '1800-123-4567', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
