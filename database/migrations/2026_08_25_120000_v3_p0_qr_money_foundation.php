<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LTP v3 P0 — QR money foundation.
 * No UI. Adds booking hold statuses, owner UPI fields, platform-fee payments, settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceCheck(
            'bookings',
            'bookings_booking_status_check',
            "booking_status IN ('confirmed', 'completed', 'cancelled', 'no_show', 'awaiting_confirmation', 'pay_on_arrival', 'expired')"
        );

        $this->replaceCheck(
            'bookings',
            'bookings_payment_mode_check',
            "payment_mode IN ('online', 'cash', 'upi', 'pay_on_turf', 'pay_on_arrival')"
        );

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'marked_paid_at')) {
                $table->timestamp('marked_paid_at')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'payment_hold_expires_at')) {
                $table->timestamp('payment_hold_expires_at')->nullable();
            }
            $table->index('payment_hold_expires_at');
            $table->index('marked_paid_at');
        });

        Schema::table('owners', function (Blueprint $table) {
            if (!Schema::hasColumn('owners', 'upi_id')) {
                $table->string('upi_id', 80)->nullable();
            }
            if (!Schema::hasColumn('owners', 'upi_qr_path')) {
                $table->string('upi_qr_path', 500)->nullable();
            }
        });

        if (!Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->enum('status', ['awaiting_admin', 'confirmed', 'rejected'])->default('awaiting_admin');
                $table->timestamp('marked_paid_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['owner_id', 'status']);
                $table->index('status');
            });
        }

        $settings = [
            ['key' => 'platform_upi_id', 'value' => '', 'type' => 'text'],
            ['key' => 'platform_qr_path', 'value' => '', 'type' => 'text'],
            ['key' => 'booking_hold_minutes', 'value' => '15', 'type' => 'number'],
            ['key' => 'booking_confirm_grace_minutes', 'value' => '120', 'type' => 'number'],
            ['key' => 'subscription_overdue_hide_days', 'value' => '14', 'type' => 'number'],
            ['key' => 'default_otp_enabled', 'value' => 'false', 'type' => 'boolean'],
        ];

        foreach ($settings as $row) {
            Setting::updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'platform_upi_id',
            'platform_qr_path',
            'booking_hold_minutes',
            'booking_confirm_grace_minutes',
            'subscription_overdue_hide_days',
        ])->delete();

        Setting::updateOrCreate(
            ['key' => 'default_otp_enabled'],
            ['value' => 'true', 'type' => 'boolean']
        );

        Schema::dropIfExists('subscription_payments');

        Schema::table('owners', function (Blueprint $table) {
            $cols = array_values(array_filter(['upi_id', 'upi_qr_path'], fn ($c) => Schema::hasColumn('owners', $c)));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['marked_paid_at', 'payment_hold_expires_at'],
                fn ($c) => Schema::hasColumn('bookings', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        $this->replaceCheck(
            'bookings',
            'bookings_booking_status_check',
            "booking_status IN ('confirmed', 'completed', 'cancelled', 'no_show')"
        );

        $this->replaceCheck(
            'bookings',
            'bookings_payment_mode_check',
            "payment_mode IN ('online', 'cash', 'upi', 'pay_on_turf')"
        );
    }

    private function replaceCheck(string $table, string $constraint, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
    }
};
