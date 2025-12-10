<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 15)->unique();
            $table->string('password')->nullable();
            $table->string('profile_image')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('pan_number', 10)->unique()->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->enum('status', ['active', 'suspended', 'pending_approval'])->default('pending_approval');
            $table->integer('total_turfs')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->string('fcm_token')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->date('joined_date')->nullable();
            $table->timestamps();
            
            $table->index('phone');
            $table->index('email');
            $table->index('status');
            $table->index('pan_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
