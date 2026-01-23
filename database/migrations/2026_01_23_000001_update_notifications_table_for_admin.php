<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('target')->nullable()->after('type');
            $table->json('user_ids')->nullable()->after('target');
            $table->timestamp('scheduled_at')->nullable()->after('user_ids');
            $table->unsignedBigInteger('sent_by')->nullable()->after('scheduled_at');
            $table->enum('status', ['sent', 'scheduled', 'failed'])->default('sent')->after('sent_by');
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->renameColumn('body', 'message');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['target', 'user_ids', 'scheduled_at', 'sent_by', 'status', 'sent_at']);
            $table->renameColumn('message', 'body');
        });
    }
};