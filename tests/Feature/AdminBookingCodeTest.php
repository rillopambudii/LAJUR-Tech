<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingCodeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function booking(): Booking
    {
        $car = Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_NOT_STARTED, 'booking_code' => 'LJR-ADMIN1',
        ]);
    }

    public function test_bookings_index_shows_booking_code(): void
    {
        $this->booking();

        $this->actingAs($this->owner())->get('/admin/bookings')
            ->assertOk()
            ->assertSee('LJR-ADMIN1');
    }

    public function test_booking_detail_shows_booking_code(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->owner())->get('/admin/bookings/'.$booking->id)
            ->assertOk()
            ->assertSee('LJR-ADMIN1');
    }
}
