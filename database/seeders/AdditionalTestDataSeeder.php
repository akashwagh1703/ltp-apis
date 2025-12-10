<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdditionalTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get the actual IDs
        $ownerId = DB::table('owners')->where('phone', '1234567890')->value('id');
        $playerId = DB::table('players')->where('phone', '9876543210')->value('id');
        $turfId = DB::table('turfs')->where('owner_id', $ownerId)->value('id');
        $slotId = DB::table('turf_slots')->where('turf_id', $turfId)->value('id');

        // Create a completed booking for review testing
        if ($playerId && $turfId && $slotId) {
            $bookingId = DB::table('bookings')->insertGetId([
                'booking_number' => 'BK' . time() . rand(1000, 9999),
                'player_id' => $playerId,
                'turf_id' => $turfId,
                'slot_id' => $slotId,
                'owner_id' => $ownerId,
                'booking_date' => now()->subDays(1),
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'slot_duration' => 60,
                'amount' => 500.00,
                'discount_amount' => 0,
                'final_amount' => 500.00,
                'booking_type' => 'online',
                'booking_status' => 'completed',
                'payment_mode' => 'online',
                'payment_status' => 'success',
                'player_name' => 'Test Player',
                'player_phone' => '9876543210',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create payment for the booking
            DB::table('payments')->insert([
                'booking_id' => $bookingId,
                'transaction_id' => 'TXN' . time(),
                'payment_gateway' => 'razorpay',
                'payment_method' => 'upi',
                'amount' => 500.00,
                'status' => 'success',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update notification IDs to be sequential starting from 1
        DB::statement('ALTER SEQUENCE notifications_id_seq RESTART WITH 1');
        DB::table('notifications')->truncate();
        
        DB::table('notifications')->insert([
            [
                'id' => 1,
                'user_type' => 'owner',
                'user_id' => $ownerId,
                'title' => 'Welcome Owner',
                'message' => 'Welcome to the platform',
                'type' => 'info',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'user_type' => 'player',
                'user_id' => $playerId ?? 1,
                'title' => 'Welcome Player',
                'message' => 'Welcome to the platform',
                'type' => 'info',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Update payout IDs
        DB::statement('ALTER SEQUENCE payouts_id_seq RESTART WITH 1');
        DB::table('payouts')->truncate();
        
        DB::table('payouts')->insert([
            'id' => 1,
            'owner_id' => $ownerId,
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
    }
}
