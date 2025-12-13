<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing free plan duration to 90 days
        DB::table('subscription_plans')
            ->where('name', 'Free Plan')
            ->update(['duration_days' => 90]);
    }

    public function down(): void
    {
        // Revert to 30 days
        DB::table('subscription_plans')
            ->where('name', 'Free Plan')
            ->update(['duration_days' => 30]);
    }
};
