<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['key' => 'commission_percentage', 'value' => '10', 'type' => 'number', 'description' => 'Platform commission percentage'],
            ['key' => 'slot_lock_minutes', 'value' => '10', 'type' => 'number', 'description' => 'Minutes to lock slot during booking'],
            ['key' => 'otp_expiry_minutes', 'value' => '10', 'type' => 'number', 'description' => 'OTP expiry time in minutes'],
            ['key' => 'cancellation_hours', 'value' => '24', 'type' => 'number', 'description' => 'Hours before booking to allow cancellation'],
            ['key' => 'app_name', 'value' => 'Let\'s Turf Play', 'type' => 'string', 'description' => 'Application name'],
            ['key' => 'support_email', 'value' => 'support@letsturf.com', 'type' => 'string', 'description' => 'Support email address'],
            ['key' => 'support_phone', 'value' => '1800-123-4567', 'type' => 'string', 'description' => 'Support phone number'],
            
            // SMS & OTP Settings
            ['key' => 'sms_enabled', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enable/Disable SMS notifications'],
            ['key' => 'default_otp_enabled', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enable default OTP (999999) for testing — must stay false in production'],
            ['key' => 'default_otp', 'value' => '999999', 'type' => 'string', 'description' => 'Default OTP for testing'],
            
            // MSG91 Settings
            ['key' => 'msg91_auth_key', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Auth Key'],
            ['key' => 'msg91_sender_id', 'value' => 'LTPLAY', 'type' => 'string', 'description' => 'MSG91 Sender ID'],
            ['key' => 'msg91_otp_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 OTP Template ID'],
            ['key' => 'msg91_booking_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Booking Template ID'],
            ['key' => 'msg91_cancel_template_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 Cancel Template ID'],
            ['key' => 'msg91_dlt_entity_id', 'value' => '', 'type' => 'string', 'description' => 'MSG91 DLT Entity ID'],
            
            // Razorpay Settings
            ['key' => 'razorpay_enabled', 'value' => 'false', 'type' => 'string', 'description' => 'Enable/Disable Razorpay payments'],
            ['key' => 'razorpay_mode', 'value' => 'test', 'type' => 'string', 'description' => 'Razorpay mode: test or live'],
            ['key' => 'razorpay_key_id', 'value' => '', 'type' => 'string', 'description' => 'Razorpay Key ID'],
            ['key' => 'razorpay_key_secret', 'value' => '', 'type' => 'string', 'description' => 'Razorpay Key Secret'],
            ['key' => 'razorpay_webhook_secret', 'value' => '', 'type' => 'string', 'description' => 'Razorpay Webhook Secret'],
            
            // Razorpay Payouts
            ['key' => 'razorpay_payouts_enabled', 'value' => 'false', 'type' => 'string', 'description' => 'Enable/Disable Razorpay payouts'],
            ['key' => 'razorpay_payout_key_id', 'value' => '', 'type' => 'string', 'description' => 'Razorpay Payout Key ID'],
            ['key' => 'razorpay_payout_key_secret', 'value' => '', 'type' => 'string', 'description' => 'Razorpay Payout Key Secret'],

            // v3 QR money (no payment gateway)
            ['key' => 'platform_upi_id', 'value' => '', 'type' => 'text', 'description' => 'LTP admin UPI ID for owner platform fees'],
            ['key' => 'platform_qr_path', 'value' => '', 'type' => 'text', 'description' => 'Public-disk path to LTP UPI QR image'],
            ['key' => 'booking_hold_minutes', 'value' => '15', 'type' => 'number', 'description' => 'Minutes to hold a slot before player marks paid'],
            ['key' => 'booking_confirm_grace_minutes', 'value' => '120', 'type' => 'number', 'description' => 'Minutes owner has to confirm after player marks paid'],
            ['key' => 'subscription_overdue_hide_days', 'value' => '14', 'type' => 'number', 'description' => 'Days after plan expiry before turf is hidden from player search'],
            ['key' => 'booking_advance_percent', 'value' => '50', 'type' => 'number', 'description' => 'Percent due now when player chooses Pay part now. 0 hides the option.'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
