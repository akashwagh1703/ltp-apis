<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if constraint exists before dropping
        $hasConstraint = DB::select(
            "SELECT 1 FROM pg_constraint WHERE conname = 'bookings_payment_status_check'"
        );
        
        if ($hasConstraint) {
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT bookings_payment_status_check");
        }
        
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('pending', 'success', 'failed', 'refunded', 'partial'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_status_check");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('pending', 'success', 'failed', 'refunded'))");
    }
};
