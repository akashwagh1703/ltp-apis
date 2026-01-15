<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('user_type', ['owner', 'player', 'admin'])->nullable();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->enum('type', ['booking', 'payment', 'review', 'promotional', 'reminder', 'general', 'cancellation'])->default('general');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'user_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
