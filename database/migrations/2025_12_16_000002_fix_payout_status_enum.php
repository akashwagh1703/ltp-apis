<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, we need to alter the enum type
        DB::statement("ALTER TABLE payouts DROP CONSTRAINT IF EXISTS payouts_status_check");
        DB::statement("ALTER TABLE payouts ADD CONSTRAINT payouts_status_check CHECK (status IN ('pending', 'processed', 'paid', 'failed'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payouts DROP CONSTRAINT IF EXISTS payouts_status_check");
        DB::statement("ALTER TABLE payouts ADD CONSTRAINT payouts_status_check CHECK (status IN ('pending', 'processing', 'paid', 'failed'))");
    }
};
