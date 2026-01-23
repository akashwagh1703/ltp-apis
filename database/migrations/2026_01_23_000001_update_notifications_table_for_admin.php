<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add missing columns that the controller expects
            if (!Schema::hasColumn('notifications', 'target')) {
                $table->string('target')->nullable()->after('type');
            }
            if (!Schema::hasColumn('notifications', 'user_ids')) {
                $table->json('user_ids')->nullable()->after('target');
            }
            if (!Schema::hasColumn('notifications', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('user_ids');
            }
            if (!Schema::hasColumn('notifications', 'sent_by')) {
                $table->unsignedBigInteger('sent_by')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('notifications', 'status')) {
                $table->enum('status', ['sent', 'scheduled', 'failed'])->default('sent')->after('sent_by');
            }
            if (!Schema::hasColumn('notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            // Rename body to message only if body column exists
            if (Schema::hasColumn('notifications', 'body') && !Schema::hasColumn('notifications', 'message')) {
                $table->renameColumn('body', 'message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['target', 'user_ids', 'scheduled_at', 'sent_by', 'status', 'sent_at']);
            if (Schema::hasColumn('notifications', 'message') && !Schema::hasColumn('notifications', 'body')) {
                $table->renameColumn('message', 'body');
            }
        });
    }
};