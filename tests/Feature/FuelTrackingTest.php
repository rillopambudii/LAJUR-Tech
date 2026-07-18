<?php

namespace Tests\Feature;

use App\Fuel\FuelService;
use App\Models\Booking;
use App\Models\Car;
use App\Models\CarMileageDaily;
use App\Models\FuelLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FuelTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function admin(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function car(array $o = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Innova Diesel', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Diesel',
            'seats' => 7, 'price_per_day' => 500000, 'is_available' => true,
            'tank_capacity_liters' => 55, 'fuel_baseline_km_per_l' => 12,
        ], $o));
    }

    private function log(Car $car, array $o = []): FuelLog
    {
        return FuelLog::create(array_merge([
            'car_id' => $car->id, 'filled_at' => '2026-07-01 08:00:00',
            'liters' => 40, 'price_per_liter' => 6800, 'total_cost' => 272000,
            'full_tank' => true,
        ], $o));
    }

    private function analyze(?int $carId = null): array
    {
        return app(FuelService::class)->analyze(
            Carbon::parse('2026-06-01'), Carbon::parse('2026-07-31'), $carId
        );
    }

    public function test_efficiency_computed_full_to_full_from_odometer(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000]);
        $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 10440, 'liters' => 40]);

        $a = $this->analyze();
        $s = $a['summaries']->first(fn ($s) => $s['car']->id === $car->id);

        $this->assertSame(11.0, $s['km_per_liter']); // 440 km / 40 L
        $this->assertSame(440, $s['km']);
        $log2 = $a['logs']->firstWhere('odometer_km', 10440);
        $this->assertSame(11.0, $log2->segment_km_per_l);
        $this->assertNotContains('guzzling', $log2->flags); // 11 >= 12 × 0.8
    }

    public function test_guzzling_flag_when_far_below_baseline(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000]);
        $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 10300, 'liters' => 40]);

        $log2 = $this->analyze()['logs']->firstWhere('odometer_km', 10300);
        $this->assertContains('guzzling', $log2->flags); // 7.5 km/L < 9.6
    }

    public function test_overfill_and_odometer_backwards_flags(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000]);
        $bad = $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 9500, 'liters' => 60]);

        $log = $this->analyze()['logs']->firstWhere('id', $bad->id);
        $this->assertContains('overfill', $log->flags);           // 60 L > tangki 55 L
        $this->assertContains('odometer_backwards', $log->flags); // 9500 < 10000
    }

    public function test_gps_fallback_when_odometer_missing(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00']);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-07-02', 'km' => 200]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-07-04', 'km' => 240]);
        $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'liters' => 40]);

        $s = $this->analyze()['summaries']->first(fn ($s) => $s['car']->id === $car->id);
        $this->assertSame(11.0, $s['km_per_liter']); // (200+240) km GPS / 40 L
    }

    public function test_gps_mismatch_flag_when_odometer_disagrees_with_gps(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-07-03', 'km' => 300]);
        $second = $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 10100]);

        $log = $this->analyze()['logs']->firstWhere('id', $second->id);
        $this->assertContains('gps_mismatch', $log->flags); // odo 100 vs GPS 300
    }

    public function test_idle_fill_flag_only_outside_booking_dates(): void
    {
        $car = $this->car();
        Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'days' => 3,
            'price_per_day' => 500000, 'total_price' => 1500000, 'status' => 'confirmed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
        $inside = $this->log($car, ['filled_at' => '2026-07-02 08:00:00']);
        $outside = $this->log($car, ['filled_at' => '2026-07-20 08:00:00']);

        $logs = $this->analyze()['logs'];
        $this->assertNotContains('idle_fill', $logs->firstWhere('id', $inside->id)->flags);
        $this->assertContains('idle_fill', $logs->firstWhere('id', $outside->id)->flags);
    }

    public function test_price_outlier_flag_against_tenant_median(): void
    {
        $car = $this->car();
        $normal = null;
        foreach (range(1, 5) as $d) {
            $normal = $this->log($car, ['filled_at' => "2026-07-0{$d} 08:00:00", 'price_per_liter' => 6800]);
        }
        $marked = $this->log($car, ['filled_at' => '2026-07-10 08:00:00', 'price_per_liter' => 10000]);

        $logs = $this->analyze()['logs'];
        $this->assertContains('price_outlier', $logs->firstWhere('id', $marked->id)->flags);
        $this->assertNotContains('price_outlier', $logs->firstWhere('id', $normal->id)->flags);
    }

    public function test_partial_fills_accumulate_into_full_to_full_segment(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000, 'liters' => 40]);
        // Isi parsial di tengah: bukan segmen sendiri, liternya ikut terbakar.
        $partial = $this->log($car, ['filled_at' => '2026-07-03 08:00:00', 'liters' => 20, 'full_tank' => false, 'total_cost' => 136000]);
        $closing = $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 10440, 'liters' => 25, 'total_cost' => 170000]);

        $a = $this->analyze();
        $logs = $a['logs'];

        $this->assertNull($logs->firstWhere('id', $partial->id)->segment_km);

        $closed = $logs->firstWhere('id', $closing->id);
        $this->assertSame(440.0, $closed->segment_km);        // odometer anchor → penutup
        $this->assertSame(45.0, $closed->segment_liters);     // 25 penuh + 20 parsial
        $this->assertSame(306000, $closed->segment_cost);     // 170rb + 136rb
        $this->assertSame(9.8, $closed->segment_km_per_l);    // 440 / 45 = 9,78
        $this->assertNotContains('guzzling', $closed->flags); // 9,78 > 12 × 0,8 = 9,6

        $s = $a['summaries']->first(fn ($s) => $s['car']->id === $car->id);
        $this->assertSame(9.8, $s['km_per_liter']);
    }

    public function test_same_day_fills_use_precise_gps_distance(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'liters' => 40]);
        // Titik GPS mentah antar dua pengisian di HARI YANG SAMA:
        // 4 lompatan × 0,01° lintang ≈ 4 × 1,11 km ≈ 4,45 km.
        foreach ([[-0.50, '09:00'], [-0.49, '09:20'], [-0.48, '09:40'], [-0.47, '10:00'], [-0.46, '10:20']] as [$lat, $t]) {
            VehiclePosition::create(['car_id' => $car->id, 'latitude' => $lat, 'longitude' => 117.15, 'speed' => 30, 'course' => 0, 'device_time' => "2026-07-01 {$t}:00"]);
        }
        $second = $this->log($car, ['filled_at' => '2026-07-01 11:00:00', 'liters' => 0.4]);

        $log = $this->analyze()['logs']->firstWhere('id', $second->id);
        $this->assertNotNull($log->segment_km); // bucket harian tak bisa; presisi bisa
        $this->assertEqualsWithDelta(11.1, $log->segment_km_per_l, 0.2); // ≈4,45 / 0,4
    }

    public function test_idle_fill_has_grace_day_for_preparation_and_return(): void
    {
        $car = $this->car();
        Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Sari', 'customer_email' => 's@x.id', 'customer_phone' => '082',
            'start_date' => '2026-07-10', 'end_date' => '2026-07-12', 'days' => 3,
            'price_per_day' => 500000, 'total_price' => 1500000, 'status' => 'confirmed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
        $prep = $this->log($car, ['filled_at' => '2026-07-09 08:00:00']);   // H-1 persiapan
        $return = $this->log($car, ['filled_at' => '2026-07-13 08:00:00']); // H+1 pengembalian
        $far = $this->log($car, ['filled_at' => '2026-07-20 08:00:00']);    // jauh di luar

        $logs = $this->analyze()['logs'];
        $this->assertNotContains('idle_fill', $logs->firstWhere('id', $prep->id)->flags);
        $this->assertNotContains('idle_fill', $logs->firstWhere('id', $return->id)->flags);
        $this->assertContains('idle_fill', $logs->firstWhere('id', $far->id)->flags);
    }

    public function test_stale_odometer_delta_zero_falls_back_to_gps(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => '2026-07-01 08:00:00', 'odometer_km' => 10000]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-07-03', 'km' => 440]);
        // Odometer salah diketik ulang sama persis — delta 0 tidak boleh menekan fallback GPS.
        $second = $this->log($car, ['filled_at' => '2026-07-05 08:00:00', 'odometer_km' => 10000, 'liters' => 40]);

        $log = $this->analyze()['logs']->firstWhere('id', $second->id);
        $this->assertSame(11.0, $log->segment_km_per_l); // GPS 440 km / 40 L
    }

    public function test_price_outlier_pool_scoped_to_fuel_type(): void
    {
        // 5 pengisian bensin mahal (~13.000) di armada.
        $bensin = $this->car(['name' => 'Camry Bensin', 'fuel_type' => 'Bensin']);
        foreach (range(1, 5) as $d) {
            $this->log($bensin, ['filled_at' => "2026-07-0{$d} 08:00:00", 'price_per_liter' => 13000]);
        }
        // Satu pengisian solar wajar (~6.800). Dgn median dicampur, ini akan
        // ter-flag palsu; dengan filter jenis BBM, tidak.
        $solar = $this->car(['name' => 'Hilux Solar', 'fuel_type' => 'Diesel']);
        $solarLog = $this->log($solar, ['filled_at' => '2026-07-10 08:00:00', 'price_per_liter' => 6800]);

        $logs = $this->analyze()['logs'];
        $this->assertNotContains('price_outlier', $logs->firstWhere('id', $solarLog->id)->flags);
    }

    public function test_price_outlier_pool_stays_tenant_wide_when_filtered_by_car(): void
    {
        $carA = $this->car();
        $carB = $this->car(['name' => 'Avanza Bensin']);
        foreach (range(1, 5) as $d) {
            $this->log($carA, ['filled_at' => "2026-07-0{$d} 08:00:00", 'price_per_liter' => 6800]);
        }
        $marked = $this->log($carB, ['filled_at' => '2026-07-10 08:00:00', 'price_per_liter' => 10000]);

        // Difilter ke carB saja: median tetap dihitung lintas mobil se-tenant.
        $logs = $this->analyze($carB->id)['logs'];
        $this->assertContains('price_outlier', $logs->firstWhere('id', $marked->id)->flags);
    }

    public function test_cannot_record_fill_for_other_tenants_car(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $foreignCar = Car::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'name' => 'Mobil Asing', 'brand' => 'X', 'type' => 'MPV',
            'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 4,
            'price_per_day' => 100000, 'is_available' => true,
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/fuel', [
                'car_id' => $foreignCar->id, 'filled_at' => '2026-07-08 09:30',
                'liters' => 40, 'price_per_liter' => 6800,
            ])
            ->assertSessionHasErrors('car_id');

        $this->assertDatabaseMissing('fuel_logs', ['car_id' => $foreignCar->id]);
    }

    public function test_computed_total_cost_never_below_one(): void
    {
        $car = $this->car();

        $this->actingAs($this->admin())
            ->post('/admin/fuel', [
                'car_id' => $car->id, 'filled_at' => '2026-07-08 09:30',
                'liters' => 0.1, 'price_per_liter' => 1, // round(0.1) = 0 → dipaksa min 1
            ])
            ->assertRedirect(route('admin.fuel.index'));

        $this->assertDatabaseHas('fuel_logs', ['car_id' => $car->id, 'total_cost' => 1]);
    }

    public function test_admin_can_record_fill_even_beyond_tank_capacity(): void
    {
        $car = $this->car();

        $this->actingAs($this->admin())
            ->post('/admin/fuel', [
                'car_id' => $car->id,
                'filled_at' => '2026-07-08 09:30',
                'liters' => 60, // > tangki 55 — tetap tersimpan, nanti ter-flag
                'price_per_liter' => 6800,
                'full_tank' => 1,
                'station' => 'SPBU 64.751.02',
            ])
            ->assertRedirect(route('admin.fuel.index'));

        $this->assertDatabaseHas('fuel_logs', [
            'car_id' => $car->id, 'total_cost' => 408000, 'station' => 'SPBU 64.751.02',
        ]);
    }

    public function test_fuel_index_renders_indicators_and_flags(): void
    {
        $car = $this->car();
        $this->log($car, ['filled_at' => now()->subDays(5), 'odometer_km' => 10000]);
        $this->log($car, ['filled_at' => now()->subDay(), 'odometer_km' => 10300, 'liters' => 40]);

        $this->actingAs($this->admin())
            ->get('/admin/fuel')
            ->assertOk()
            ->assertSee('Innova Diesel')
            ->assertSee('Konsumsi aktual')
            ->assertSee('Konsumsi jauh lebih boros dari baseline');
    }

    public function test_fuel_index_nudges_when_active_car_missing_specs(): void
    {
        $this->car(['name' => 'Camry Kosong', 'tank_capacity_liters' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/fuel')
            ->assertOk()
            ->assertSee('Deteksi kebocoran belum aktif')
            ->assertSee('Camry Kosong');
    }

    public function test_fuel_index_no_nudge_when_specs_complete_or_car_inactive(): void
    {
        // Mobil aktif dengan spesifikasi lengkap: tidak perlu nudge.
        $this->car(['name' => 'Lengkap']);
        // Mobil nonaktif walau spesifikasi kosong: tidak di-nudge.
        $this->car(['name' => 'Nonaktif Kosong', 'is_available' => false, 'fuel_baseline_km_per_l' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/fuel')
            ->assertOk()
            ->assertDontSee('Deteksi kebocoran belum aktif');
    }

    public function test_destroy_deletes_log(): void
    {
        $log = $this->log($this->car());

        $this->actingAs($this->admin())
            ->delete("/admin/fuel/{$log->id}")
            ->assertRedirect(route('admin.fuel.index'));

        $this->assertDatabaseMissing('fuel_logs', ['id' => $log->id]);
    }

    public function test_guest_cannot_access_fuel_pages(): void
    {
        $this->get('/admin/fuel')->assertRedirect('/login');
    }
}
