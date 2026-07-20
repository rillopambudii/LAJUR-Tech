<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Alamat tujuan teks bebas — dipakai tombol Maps di dashboard driver
            // (link Google Maps universal, tanpa koordinat/API key).
            $table->string('destination')->nullable()->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('destination');
        });
    }
};
