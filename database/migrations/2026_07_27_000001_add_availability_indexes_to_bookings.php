<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Query terpanas (Car::isAvailableForRange, kalender, utilisasi) memfilter
 * tenant_id + car_id + status + rentang tanggal, tapi belum ada indeks yang
 * mencakup predikat tanggal → degradasi jadi scan begitu booking menumpuk.
 * Tambah indeks komposit; juga untuk cek tumpang-tindih penugasan driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['tenant_id', 'car_id', 'start_date', 'end_date'], 'bookings_availability_idx');
            $table->index(['driver_id', 'start_date', 'end_date'], 'bookings_driver_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_availability_idx');
            $table->dropIndex('bookings_driver_dates_idx');
        });
    }
};
