<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0)->after('final_amount');
            $table->decimal('pending_amount', 10, 2)->default(0)->after('paid_amount');
            $table->decimal('advance_percentage', 5, 2)->nullable()->after('pending_amount');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'pending_amount', 'advance_percentage']);
        });
    }
};
