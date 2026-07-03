<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            // Maps this car to a device in the Traccar gateway (filled during integration).
            $table->string('traccar_device_id')->nullable()->after('plate_number');
            $table->index('traccar_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['traccar_device_id']);
            $table->dropColumn('traccar_device_id');
        });
    }
};
