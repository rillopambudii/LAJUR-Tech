<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverFuelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $driver;
    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);

        $this->driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Rahmat', 'email' => 'rahmat@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
        ]);

        $this->car = Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
    }

    private function bookingToday(Car $car, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $this->driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '0811',
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'days' => 3, 'price_per_day' => 250000, 'total_price' => 750000, 'status' => $status,
        ]);
    }

    public function test_driver_sees_ongoing_task_car_on_form(): void
    {
        $this->bookingToday($this->car);

        $this->actingAs($this->driver)->get('/driver/bbm')
            ->assertOk()
            ->assertSee('Avanza');
    }

    public function test_form_shows_empty_state_without_ongoing_task(): void
    {
        $this->actingAs($this->driver)->get('/driver/bbm')
            ->assertOk()
            ->assertSee('Tidak ada tugas yang sedang berjalan');
    }

    public function test_driver_can_record_fuel_with_receipt_for_ongoing_car(): void
    {
        Storage::fake('public');
        $this->bookingToday($this->car);

        $this->actingAs($this->driver)->post('/driver/bbm', [
            'car_id' => $this->car->id,
            'filled_at' => now()->format('Y-m-d H:i:s'),
            'liters' => 30,
            'price_per_liter' => 10000,
            'odometer_km' => 12345,
            'full_tank' => '1',
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ])->assertRedirect(route('driver.dashboard'));

        $log = FuelLog::first();
        $this->assertNotNull($log);
        $this->assertSame($this->car->id, $log->car_id);
        $this->assertSame($this->driver->id, $log->created_by);
        $this->assertSame(300000, $log->total_cost);
        $this->assertNotNull($log->receipt_path);
        Storage::disk('public')->assertExists($log->receipt_path);
    }

    public function test_receipt_is_required_for_driver(): void
    {
        $this->bookingToday($this->car);

        $this->actingAs($this->driver)->post('/driver/bbm', [
            'car_id' => $this->car->id,
            'filled_at' => now()->format('Y-m-d H:i:s'),
            'liters' => 30,
            'price_per_liter' => 10000,
        ])->assertSessionHasErrors('receipt');

        $this->assertSame(0, FuelLog::count());
    }

    public function test_driver_cannot_record_fuel_for_car_not_currently_assigned(): void
    {
        // Booking beda mobil, bukan tugas hari ini.
        $otherCar = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);
        $this->bookingToday($this->car); // tugas berjalan = Avanza, bukan Xenia

        Storage::fake('public');
        $this->actingAs($this->driver)->post('/driver/bbm', [
            'car_id' => $otherCar->id,
            'filled_at' => now()->format('Y-m-d H:i:s'),
            'liters' => 30,
            'price_per_liter' => 10000,
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ])->assertSessionHasErrors('car_id');

        $this->assertSame(0, FuelLog::count());
    }

    public function test_driver_cannot_record_fuel_for_upcoming_not_yet_started_task(): void
    {
        Booking::create([
            'car_id' => $this->car->id, 'driver_id' => $this->driver->id, 'car_name' => $this->car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '0811',
            'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
            'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'confirmed',
        ]);

        Storage::fake('public');
        $this->actingAs($this->driver)->post('/driver/bbm', [
            'car_id' => $this->car->id,
            'filled_at' => now()->format('Y-m-d H:i:s'),
            'liters' => 30,
            'price_per_liter' => 10000,
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ])->assertSessionHasErrors('car_id');
    }

    public function test_driver_has_no_delete_route_for_fuel_logs(): void
    {
        Storage::fake('public');
        $this->bookingToday($this->car);
        $log = FuelLog::create([
            'car_id' => $this->car->id, 'filled_at' => now(), 'liters' => 30,
            'price_per_liter' => 10000, 'total_cost' => 300000, 'created_by' => $this->driver->id,
        ]);

        // Rute hapus hanya ada di namespace admin (auth+admin), driver ditolak.
        $this->actingAs($this->driver)->delete("/admin/fuel/{$log->id}")->assertForbidden();
        $this->assertNotNull(FuelLog::find($log->id));
    }

    public function test_admin_can_see_driver_submitted_log_with_receipt_link(): void
    {
        Storage::fake('public');
        $this->bookingToday($this->car);
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($this->driver)->post('/driver/bbm', [
            'car_id' => $this->car->id,
            'filled_at' => now()->format('Y-m-d H:i:s'),
            'liters' => 30, 'price_per_liter' => 10000,
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ]);

        $this->actingAs($owner)->get('/admin/fuel')
            ->assertOk()
            ->assertSee('Rahmat')
            ->assertSee('driver');
    }
}
