<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('default_otp_enabled', 'true', 'boolean');
        Setting::set('default_otp', '999999', 'string');
    }

    public function down(): void
    {
        Setting::set('default_otp_enabled', 'false', 'boolean');
    }
};
