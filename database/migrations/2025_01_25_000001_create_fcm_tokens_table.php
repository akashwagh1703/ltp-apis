<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['owner', 'player']);
            $table->text('token');
            $table->string('device_type')->default('android');
            $table->timestamps();
            
            $table->unique(['user_id', 'user_type', 'token']);
            $table->index(['user_id', 'user_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
