<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedInteger('odometer_baseline_km')->default(0)->after('service_due_date');
            $table->timestamp('baseline_at')->nullable()->after('odometer_baseline_km');
            $table->unsignedInteger('service_interval_km')->nullable()->after('baseline_at');
            $table->unsignedInteger('service_last_km')->nullable()->after('service_interval_km');
            $table->timestamp('mileage_synced_at')->nullable()->after('service_last_km');
        });

        Schema::create('car_mileage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('km')->default(0);
            $table->timestamps();
            $table->unique(['car_id', 'date']);
            $table->index(['tenant_id', 'car_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_mileage_daily');
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['odometer_baseline_km', 'baseline_at', 'service_interval_km', 'service_last_km', 'mileage_synced_at']);
        });
    }
};
