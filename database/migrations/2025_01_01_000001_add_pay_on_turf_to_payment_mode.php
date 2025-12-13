<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_mode_check");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_mode_check CHECK (payment_mode IN ('online', 'cash', 'upi', 'pay_on_turf'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_mode_check");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_mode_check CHECK (payment_mode IN ('online', 'cash', 'upi'))");
    }
};
