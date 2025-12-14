<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('platform_commission', 8, 2)->default(0)->after('final_amount');
            $table->decimal('owner_payout', 8, 2)->default(0)->after('platform_commission');
            $table->decimal('commission_rate', 5, 2)->default(5.00)->after('owner_payout');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['platform_commission', 'owner_payout', 'commission_rate']);
        });
    }
};
