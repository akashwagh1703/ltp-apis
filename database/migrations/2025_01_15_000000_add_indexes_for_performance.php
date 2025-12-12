<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // PLAYERS
        $this->addIndexSafe('players', 'phone');
        $this->addIndexSafe('players', 'email');
        $this->addIndexSafe('players', 'status');

        // OWNERS
        $this->addIndexSafe('owners', 'phone');
        $this->addIndexSafe('owners', 'email');
        $this->addIndexSafe('owners', 'status');

        // TURFS
        $this->addIndexSafe('turfs', 'owner_id');
        $this->addIndexSafe('turfs', 'status');
        $this->addIndexSafe('turfs', 'city');
        $this->addIndexSafe('turfs', 'is_featured');
        $this->addIndexSafeMultiple('turfs', ['latitude', 'longitude']);

        // BOOKINGS
        $this->addIndexSafe('bookings', 'player_id');
        $this->addIndexSafe('bookings', 'turf_id');
        $this->addIndexSafe('bookings', 'owner_id');
        $this->addIndexSafe('bookings', 'booking_date');
        $this->addIndexSafe('bookings', 'booking_status');
        $this->addIndexSafe('bookings', 'payment_status');
        $this->addIndexSafe('bookings', 'booking_number');

        // TURF SLOTS
        $this->addIndexSafe('turf_slots', 'turf_id');
        $this->addIndexSafe('turf_slots', 'date');
        $this->addIndexSafe('turf_slots', 'status');
        $this->addIndexSafeMultiple('turf_slots', ['turf_id', 'date', 'status']);

        // REVIEWS
        $this->addIndexSafe('reviews', 'turf_id');
        $this->addIndexSafe('reviews', 'player_id');
        // $this->addIndexSafe('reviews', 'status');

        // COUPONS
        $this->addIndexSafe('coupons', 'code');
        $this->addIndexSafe('coupons', 'is_active');
        $this->addIndexSafeMultiple('coupons', ['valid_from', 'valid_to']);

        // OTP
        $this->addIndexSafe('otps', 'phone');
        $this->addIndexSafe('otps', 'expires_at');
    }

    public function down()
    {
        // Nothing required for production
    }

    private function addIndexSafe($table, $column)
    {
        $indexName = "{$table}_{$column}_index";

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.relname = '{$indexName}'
                ) THEN
                    CREATE INDEX {$indexName} ON {$table} ({$column});
                END IF;
            END$$;
        ");
    }

    private function addIndexSafeMultiple($table, array $columns)
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        $columnsStr = implode(', ', $columns);

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.relname = '{$indexName}'
                ) THEN
                    CREATE INDEX {$indexName} ON {$table} ({$columnsStr});
                END IF;
            END$$;
        ");
    }
};
