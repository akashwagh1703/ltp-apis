<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('default_otp_enabled', 'false', 'boolean');
    }

    public function down(): void
    {
        // Keep OTP off; do not re-enable 999999.
    }
};
