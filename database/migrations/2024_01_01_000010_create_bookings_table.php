<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->foreignId('slot_id')->constrained('turf_slots')->onDelete('cascade');
            $table->foreignId('player_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('owner_id')->constrained()->onDelete('cascade');
            $table->enum('booking_type', ['online', 'offline'])->default('online');
            $table->string('player_name');
            $table->string('player_phone', 15);
            $table->string('player_email')->nullable();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration');
            $table->decimal('amount', 8, 2);
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->decimal('final_amount', 8, 2);
            $table->enum('payment_mode', ['online', 'cash', 'upi'])->default('online');
            $table->enum('payment_status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->enum('booking_status', ['confirmed', 'completed', 'cancelled', 'no_show'])->default('confirmed');
            $table->text('cancellation_reason')->nullable();
            $table->enum('cancelled_by', ['admin', 'player', 'owner'])->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('refund_amount', 8, 2)->nullable();
            $table->string('refund_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('booking_number');
            $table->index('turf_id');
            $table->index('player_id');
            $table->index('owner_id');
            $table->index('booking_date');
            $table->index('booking_status');
            $table->index('booking_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
