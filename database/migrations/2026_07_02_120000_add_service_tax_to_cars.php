<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('plate_number', 20)->nullable()->after('name');
            $table->date('tax_due_date')->nullable()->after('description');    // jatuh tempo pajak (STNK)
            $table->date('service_due_date')->nullable()->after('tax_due_date'); // jadwal servis berikutnya
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['plate_number', 'tax_due_date', 'service_due_date']);
        });
    }
};
