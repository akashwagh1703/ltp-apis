<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add 'cancellation' to notification type enum
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('booking', 'payment', 'review', 'promotional', 'reminder', 'general', 'cancellation') DEFAULT 'general'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('booking', 'payment', 'review', 'promotional', 'reminder', 'general') DEFAULT 'general'");
    }
};
