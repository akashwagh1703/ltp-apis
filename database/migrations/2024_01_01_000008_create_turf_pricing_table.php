<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turf_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->enum('day_type', ['weekday', 'weekend']);
            $table->enum('time_slot', ['morning', 'afternoon', 'evening', 'night']);
            $table->decimal('price', 8, 2);
            $table->timestamps();
            
            $table->index('turf_id');
            $table->unique(['turf_id', 'day_type', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turf_pricing');
    }
};
