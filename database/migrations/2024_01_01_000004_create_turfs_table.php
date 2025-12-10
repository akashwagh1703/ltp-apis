<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('sport_type');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 6);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('size')->nullable();
            $table->integer('capacity')->nullable();
            $table->time('opening_time');
            $table->time('closing_time');
            $table->integer('slot_duration')->default(60);
            $table->enum('pricing_type', ['uniform', 'dynamic'])->default('uniform');
            $table->decimal('uniform_price', 8, 2)->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->index('owner_id');
            $table->index('status');
            $table->index('sport_type');
            $table->index('city');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turfs');
    }
};
