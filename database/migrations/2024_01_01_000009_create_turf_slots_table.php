<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turf_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price', 8, 2);
            $table->enum('status', ['available', 'booked_online', 'booked_offline', 'blocked'])->default('available');
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
            
            $table->index('turf_id');
            $table->index('date');
            $table->index('status');
            $table->unique(['turf_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turf_slots');
    }
};
