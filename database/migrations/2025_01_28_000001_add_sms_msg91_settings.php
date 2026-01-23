<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up()
    {
        $settings = [
            // SMS & OTP Settings
            ['key' => 'sms_enabled', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enable/Disable SMS notifications'],
            ['key' => 'default_otp_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable default OTP (999999) for testing'],
            ['key' => 'default_otp', 'value' => '999999', 'type' => 'string', 'description' => 'Default OTP for testing'],
            
            // MSG91 Settings
            ['key' => 'msg91_auth_key', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Auth Key'],
            ['key' => 'msg91_sender_id', 'value' => 'LTPLAY', 'type' => 'string', 'description' => 'MSG91 Sender ID'],
            ['key' => 'msg91_otp_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 OTP Template ID'],
            ['key' => 'msg91_booking_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Booking Template ID'],
            ['key' => 'msg91_cancel_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Cancel Template ID'],
            ['key' => 'msg91_dlt_entity_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 DLT Entity ID'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down()
    {
        $keys = [
            'sms_enabled', 'default_otp_enabled', 'default_otp',
            'msg91_auth_key', 'msg91_sender_id', 'msg91_otp_template_id',
            'msg91_booking_template_id', 'msg91_cancel_template_id', 'msg91_dlt_entity_id'
        ];

        Setting::whereIn('key', $keys)->delete();
    }
};