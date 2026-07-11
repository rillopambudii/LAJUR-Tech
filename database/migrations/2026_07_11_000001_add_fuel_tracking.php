<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedSmallInteger('tank_capacity_liters')->nullable()->after('fuel_type');
            $table->decimal('fuel_baseline_km_per_l', 5, 2)->nullable()->after('tank_capacity_liters');
        });

        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->dateTime('filled_at');
            $table->decimal('liters', 8, 2);
            $table->unsignedInteger('price_per_liter');
            $table->unsignedInteger('total_cost');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->boolean('full_tank')->default(true);
            $table->string('station')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'car_id', 'filled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['tank_capacity_liters', 'fuel_baseline_km_per_l']);
        });
    }
};
