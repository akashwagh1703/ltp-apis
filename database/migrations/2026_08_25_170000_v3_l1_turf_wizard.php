<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE turfs DROP CONSTRAINT IF EXISTS turfs_status_check');
        DB::statement("ALTER TABLE turfs ADD CONSTRAINT turfs_status_check CHECK (status IN ('draft', 'pending', 'approved', 'suspended'))");

        Schema::table('turfs', function (Blueprint $table) {
            if (!Schema::hasColumn('turfs', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            if (!Schema::hasColumn('turfs', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('turfs', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['rejection_reason', 'submitted_at'],
                fn ($c) => Schema::hasColumn('turfs', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        DB::statement('ALTER TABLE turfs DROP CONSTRAINT IF EXISTS turfs_status_check');
        DB::statement("ALTER TABLE turfs ADD CONSTRAINT turfs_status_check CHECK (status IN ('pending', 'approved', 'suspended'))");
    }
};
