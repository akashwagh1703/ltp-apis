<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turf_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->string('amenity_name');
            $table->timestamps();
            
            $table->index('turf_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turf_amenities');
    }
};
