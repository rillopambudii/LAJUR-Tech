<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            // Foto struk — wajib saat driver yang mencatat (bukti, penyeimbang
            // insentif skimming), opsional saat admin/owner yang mencatat sendiri.
            $table->string('receipt_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
