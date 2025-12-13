<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free Plan',
                'type' => 'monthly',
                'price' => 0.00,
                'duration_days' => 90,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Monthly Plan',
                'type' => 'monthly',
                'price' => 199.00,
                'duration_days' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Yearly Plan',
                'type' => 'yearly',
                'price' => 999.00,
                'duration_days' => 365,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
