<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE owners DROP CONSTRAINT IF EXISTS owners_status_check");
        DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_status_check CHECK (status IN ('active', 'suspended', 'pending_approval', 'inactive', 'deleted'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE owners DROP CONSTRAINT IF EXISTS owners_status_check");
        DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_status_check CHECK (status IN ('active', 'suspended', 'pending_approval'))");
    }
};
