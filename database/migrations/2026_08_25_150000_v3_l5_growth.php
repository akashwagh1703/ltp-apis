<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('turf_id')->constrained('turfs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['player_id', 'turf_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('discount_amount')->constrained('coupons')->nullOnDelete();
            }
        });

        Setting::updateOrCreate(
            ['key' => 'booking_advance_percent'],
            [
                'value' => '50',
                'type' => 'number',
            ]
        );
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
        });
        Schema::dropIfExists('player_favorites');
    }
};
