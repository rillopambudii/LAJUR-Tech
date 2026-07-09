<?php

namespace Tests\Feature;

use App\Mileage\MileageService;
use App\Models\Booking;
use App\Models\Car;
use App\Models\CarMileageDaily;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MileageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function car(array $o = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ], $o));
    }

    private function pos(Car $car, float $lat, float $lng, string $time): void
    {
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => $lat, 'longitude' => $lng, 'speed' => 30, 'course' => 90, 'device_time' => $time]);
    }

    public function test_sync_sums_daily_km_and_filters_noise(): void
    {
        $car = $this->car();
        // ~1.11 km north between two points on 2026-08-11 (0.01 deg lat ~ 1.11 km)
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');
        // jitter: ~1 m — excluded
        $this->pos($car, -0.490005, 117.15, '2026-08-11 08:11:00');
        // teleport: > 8 km — excluded
        $this->pos($car, 0.00, 117.15, '2026-08-11 08:20:00');

        (new MileageService())->syncCar($car->fresh());

        $row = CarMileageDaily::where('car_id', $car->id)->where('date', '2026-08-11')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(1, $row->km, 1); // ~1 km (jitter+teleport excluded)
    }

    public function test_sync_is_idempotent(): void
    {
        $car = $this->car();
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');
        $svc = new MileageService();
        $svc->syncCar($car->fresh());
        $svc->syncCar($car->fresh());
        $this->assertSame(1, CarMileageDaily::where('car_id', $car->id)->count());
    }

    public function test_odometer_and_service_km_status(): void
    {
        $car = $this->car(['odometer_baseline_km' => 100000, 'service_interval_km' => 5000, 'service_last_km' => 104600]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 100]);

        $car->refresh();
        $this->assertSame(100100, $car->odometerKm());        // 100000 + 100
        // next service at 104600 + 5000 = 109600; until = 109600 - 100100 = 9500 → ok
        $this->assertSame('ok', $car->serviceKmStatus());

        $car->service_last_km = 95500;
        $car->save(); // next = 100500; until = 400 → soon
        $this->assertSame('soon', $car->refresh()->serviceKmStatus());
        $this->assertTrue($car->hasDueReminder());
    }

    public function test_booking_distance_km_sums_window(): void
    {
        $car = $this->car();
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 3,
            'price_per_day' => 300000, 'total_price' => 900000, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_COMPLETED, 'booking_code' => Booking::generateBookingCode(),
        ]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 40]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-12', 'km' => 25]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-20', 'km' => 99]); // outside

        $this->assertSame(65, $booking->distanceKm());
    }

    public function test_mileage_sync_command_runs(): void
    {
        $car = $this->car();
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');

        $this->artisan('mileage:sync')->assertExitCode(0);

        $this->assertSame(1, CarMileageDaily::where('car_id', $car->id)->count());
    }

    public function test_cars_index_shows_odometer(): void
    {
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
        $car = $this->car(['odometer_baseline_km' => 80000]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 120]);

        $this->actingAs($owner)->get('/admin/cars')->assertOk()->assertSee('80.120');
    }
}
