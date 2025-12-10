<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 15)->unique();
            $table->string('email')->nullable();
            $table->string('profile_image')->nullable();
            $table->enum('language', ['en', 'hi', 'mr'])->default('en');
            $table->string('fcm_token')->nullable();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            
            $table->index('phone');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
