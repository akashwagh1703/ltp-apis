<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE turfs DROP CONSTRAINT IF EXISTS turfs_status_check');
        DB::statement('ALTER TABLE turfs ALTER COLUMN status TYPE varchar(32) USING status::text');
        DB::statement("ALTER TABLE turfs ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE turfs ADD CONSTRAINT turfs_status_check CHECK (status IN ('draft', 'pending', 'approved', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE turfs DROP CONSTRAINT IF EXISTS turfs_status_check');
        DB::statement("ALTER TABLE turfs ADD CONSTRAINT turfs_status_check CHECK (status IN ('pending', 'approved', 'suspended'))");
    }
};
