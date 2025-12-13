<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert free plan
        DB::table('subscription_plans')->insert([
            'name' => 'Free Plan',
            'type' => 'monthly',
            'price' => 0.00,
            'duration_days' => 90,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('name', 'Free Plan')->delete();
    }
};
