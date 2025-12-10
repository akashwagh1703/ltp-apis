<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test turf
        $turfId = DB::table('turfs')->insertGetId([
            'owner_id' => 1,
            'name' => 'Test Cricket Turf',
            'description' => 'Premium cricket turf for testing',
            'sport_type' => 'Cricket',
            'address_line1' => 'Test Address Line 1',
            'address_line2' => 'Test Address Line 2',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'size' => '100x50',
            'capacity' => 22,
            'opening_time' => '06:00:00',
            'closing_time' => '23:00:00',
            'slot_duration' => 60,
            'pricing_type' => 'uniform',
            'uniform_price' => 500.00,
            'rating' => 4.5,
            'total_reviews' => 0,
            'status' => 'approved',
            'is_featured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test slot
        $slotId = DB::table('turf_slots')->insertGetId([
            'turf_id' => $turfId,
            'date' => '2024-01-20',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'price' => 500.00,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test coupon
        DB::table('coupons')->insertOrIgnore([
            'code' => 'TEST',
            'description' => 'Test coupon - 10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_booking_amount' => 100.00,
            'max_discount' => 100.00,
            'usage_limit' => 100,
            'used_count' => 0,
            'valid_from' => '2024-01-01',
            'valid_to' => '2025-12-31',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test payout
        DB::table('payouts')->insert([
            'owner_id' => 1,
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'total_bookings' => 5,
            'total_amount' => 2500.00,
            'commission_percentage' => 10.00,
            'commission_amount' => 250.00,
            'settlement_amount' => 2250.00,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test notifications
        DB::table('notifications')->insertOrIgnore([
            [
                'user_type' => 'owner',
                'user_id' => 1,
                'title' => 'Welcome',
                'message' => 'Welcome to the platform',
                'type' => 'info',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_type' => 'player',
                'user_id' => 1,
                'title' => 'Welcome',
                'message' => 'Welcome to the platform',
                'type' => 'info',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create test banner
        DB::table('banners')->insertOrIgnore([
            'title' => 'Test Banner',
            'image_path' => '/images/test-banner.jpg',
            'link' => null,
            'target_audience' => 'all',
            'order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test FAQ
        DB::table('faqs')->insertOrIgnore([
            'question' => 'How to book a turf?',
            'answer' => 'Select a turf, choose a slot, and make payment.',
            'category' => 'general',
            'order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
