<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['owner', 'player']);
            $table->string('fcm_token')->nullable();
            $table->enum('status', ['success', 'failed', 'invalid_token']);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            
            $table->index(['user_id', 'user_type']);
            $table->index('status');
            $table->index('sent_at');
            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_logs');
    }
};
