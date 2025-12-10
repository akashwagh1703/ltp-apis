<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned();
            $table->text('review_text')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            
            $table->index('turf_id');
            $table->index('player_id');
            $table->unique(['booking_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
