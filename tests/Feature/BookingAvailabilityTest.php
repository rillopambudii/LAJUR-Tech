<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // The default tenant is created by the migration; scope the process to it
        // so factory rows and the public request (resolved to "lajur") line up.
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function makeCar(array $overrides = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ], $overrides));
    }

    private function book(Car $car, string $start, string $end, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '0811',
            'start_date' => $start, 'end_date' => $end, 'days' => 1,
            'price_per_day' => 300000, 'total_price' => 300000, 'status' => $status,
        ]);
    }

    public function test_overlapping_active_booking_makes_car_unavailable(): void
    {
        $car = $this->makeCar();
        $this->book($car, '2026-08-10', '2026-08-15', 'confirmed');

        $this->assertFalse($car->isAvailableForRange('2026-08-12', '2026-08-18')); // overlaps
        $this->assertTrue($car->isAvailableForRange('2026-08-16', '2026-08-20'));  // after
        $this->assertTrue($car->isAvailableForRange('2026-08-01', '2026-08-09'));  // before
    }

    public function test_cancelled_booking_does_not_block(): void
    {
        $car = $this->makeCar();
        $this->book($car, '2026-08-10', '2026-08-15', 'cancelled');

        $this->assertTrue($car->isAvailableForRange('2026-08-10', '2026-08-15'));
    }

    public function test_public_booking_is_rejected_for_overlapping_dates(): void
    {
        $car = $this->makeCar();
        $this->book($car, '2026-08-10', '2026-08-15', 'confirmed');

        $response = $this->from('/')->post('/booking', [
            'car_id' => $car->id,
            'customer_name' => 'Ani',
            'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-14',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['start_date'], null, 'booking');
        // No second booking was created.
        $this->assertSame(1, Booking::where('car_id', $car->id)->count());
    }

    public function test_public_booking_succeeds_for_free_dates(): void
    {
        $car = $this->makeCar();
        $this->book($car, '2026-08-10', '2026-08-15', 'confirmed');

        $response = $this->post('/booking', [
            'car_id' => $car->id,
            'customer_name' => 'Ani',
            'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-22',
        ]);

        $response->assertSessionHas('booking_success');
        $this->assertSame(2, Booking::where('car_id', $car->id)->count());
    }

    public function test_admin_calendar_page_loads(): void
    {
        $this->makeCar();
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'secret', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/admin/calendar')->assertOk();
    }
}
