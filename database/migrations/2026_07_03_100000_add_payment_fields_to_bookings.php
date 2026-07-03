<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // unpaid / pending / paid / failed / expired
            $table->string('payment_status')->default('unpaid')->after('status');
            // gateway order id (e.g. Midtrans order_id), unique per checkout attempt
            $table->string('payment_ref')->nullable()->unique()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_ref');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_ref', 'paid_at']);
        });
    }
};
