<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->string('car_name'); // snapshot of car name at booking time
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days');
            $table->unsignedBigInteger('price_per_day'); // snapshot of price at booking time
            $table->unsignedBigInteger('total_price'); // days * price_per_day
            $table->string('status')->default('pending'); // pending / confirmed / completed / cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
