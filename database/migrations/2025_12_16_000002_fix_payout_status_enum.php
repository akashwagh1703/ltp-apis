<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payouts DROP CONSTRAINT IF EXISTS payouts_status_check");
        DB::statement("ALTER TABLE payouts ADD CONSTRAINT payouts_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'processed'::text, 'paid'::text, 'failed'::text]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payouts DROP CONSTRAINT IF EXISTS payouts_status_check");
        DB::statement("ALTER TABLE payouts ADD CONSTRAINT payouts_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'processing'::text, 'paid'::text, 'failed'::text]))");
    }
};
