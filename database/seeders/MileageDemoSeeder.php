<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMileageDaily;
use Illuminate\Database\Seeder;

/**
 * Demo data for the mileage system: gives every car a baseline odometer, a
 * service interval, and ~14 days of daily mileage so /admin/cars shows real
 * odometer figures and some "servis dalam X km" warnings. Run manually:
 *   php artisan db:seed --class=MileageDemoSeeder
 */
class MileageDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Car::all() as $car) {
            $baseline = 50000 + ($car->id * 7919) % 80000; // deterministic-ish
            $interval = 5000;
            $car->forceFill([
                'odometer_baseline_km' => $baseline,
                'baseline_at' => now(),
                'service_interval_km' => $interval,
            ])->save();

            $total = 0;
            for ($d = 13; $d >= 0; $d--) {
                $km = 20 + (($car->id + $d) % 60);
                $total += $km;
                CarMileageDaily::updateOrCreate(
                    ['car_id' => $car->id, 'date' => now()->subDays($d)->toDateString()],
                    ['tenant_id' => $car->tenant_id, 'km' => $km]
                );
            }

            // Put last service so some cars land "due soon".
            $odometer = $baseline + $total;
            $car->forceFill(['service_last_km' => max(0, $odometer - $interval + (($car->id % 2) ? 300 : 3000))])->save();
        }
    }
}
