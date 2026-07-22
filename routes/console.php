<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly: recompute per-car daily mileage from GPS positions.
Schedule::command('mileage:sync')->dailyAt('01:00');

// Daily: downgrade tenants whose 14-day trial has ended.
Schedule::command('tenants:check-trial')->dailyAt('02:00');

// Daily: cadangkan seluruh database (menyimpan 14 backup terbaru).
// Dijalankan sebelum tugas lain agar isinya mencerminkan keadaan sebelum
// perubahan otomatis hari itu. Tak ada model yang pakai SoftDeletes, jadi ini
// SATU-SATUNYA jaring pengaman terhadap penghapusan tak sengaja.
Schedule::command('db:backup')->dailyAt('00:30')->withoutOverlapping();
