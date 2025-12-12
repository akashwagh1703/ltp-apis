<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->index('phone');
            $table->index('email');
            $table->index('status');
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->index('phone');
            $table->index('email');
            $table->index('status');
        });

        Schema::table('turfs', function (Blueprint $table) {
            $table->index('owner_id');
            $table->index('status');
            $table->index('city');
            $table->index('is_featured');
            $table->index(['latitude', 'longitude']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('player_id');
            $table->index('turf_id');
            $table->index('owner_id');
            $table->index('booking_date');
            $table->index('booking_status');
            $table->index('payment_status');
            $table->index('booking_number');
        });

        Schema::table('turf_slots', function (Blueprint $table) {
            $table->index('turf_id');
            $table->index('date');
            $table->index('status');
            $table->index(['turf_id', 'date', 'status']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('turf_id');
            $table->index('player_id');
            $table->index('status');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->index('code');
            $table->index('status');
            $table->index(['valid_from', 'valid_to']);
        });

        Schema::table('otps', function (Blueprint $table) {
            $table->index('phone');
            $table->index(['phone', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropIndex(['status']);
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropIndex(['status']);
        });

        Schema::table('turfs', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['city']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['latitude', 'longitude']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['player_id']);
            $table->dropIndex(['turf_id']);
            $table->dropIndex(['owner_id']);
            $table->dropIndex(['booking_date']);
            $table->dropIndex(['booking_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['booking_number']);
        });

        Schema::table('turf_slots', function (Blueprint $table) {
            $table->dropIndex(['turf_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['turf_id', 'date', 'status']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['turf_id']);
            $table->dropIndex(['player_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex(['status']);
            $table->dropIndex(['valid_from', 'valid_to']);
        });

        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['phone', 'purpose']);
            $table->dropIndex(['expires_at']);
        });
    }
};
