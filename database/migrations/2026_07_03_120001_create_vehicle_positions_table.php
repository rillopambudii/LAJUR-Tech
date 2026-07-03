<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('speed')->nullable();   // km/h
            $table->unsignedSmallInteger('course')->nullable();  // heading 0-359
            $table->timestamp('device_time')->nullable();        // time reported by the tracker
            $table->timestamps();

            $table->index(['tenant_id', 'car_id', 'device_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};
