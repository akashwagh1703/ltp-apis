<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Owner2TurfSeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = DB::table('owners')->where('phone', '1234567890')->value('id');
        
        if (!$ownerId) {
            echo "Owner not found\n";
            return;
        }

        $turfId = DB::table('turfs')->insertGetId([
            'owner_id' => $ownerId,
            'name' => 'Owner 2 Test Turf',
            'description' => 'Test turf for owner 2',
            'sport_type' => 'Cricket',
            'address_line1' => 'Test Address',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'opening_time' => '06:00:00',
            'closing_time' => '23:00:00',
            'slot_duration' => 60,
            'pricing_type' => 'uniform',
            'uniform_price' => 500.00,
            'status' => 'approved',
            'is_featured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create slots for this turf
        DB::table('turf_slots')->insert([
            'turf_id' => $turfId,
            'date' => '2024-01-20',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'price' => 500.00,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "Created turf ID: $turfId for owner ID: $ownerId\n";
    }
}
