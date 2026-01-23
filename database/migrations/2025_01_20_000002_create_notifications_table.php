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
            $table->string('title');
            $table->text('message');
            $table->enum('target', ['all', 'owners', 'players', 'specific']);
            $table->json('user_ids')->nullable();
            $table->string('user_type')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('sent_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['scheduled', 'sent', 'failed'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->json('delivery_stats')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
            $table->index('sent_by');
            $table->index('target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};