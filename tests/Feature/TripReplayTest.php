<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripReplayTest extends TestCase
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

    private function car(): Car
    {
        return Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);
    }

    private function booking(Car $car, array $o = []): Booking
    {
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_COMPLETED, 'booking_code' => Booking::generateBookingCode(),
        ], $o));
    }

    public function test_replay_returns_points_in_window(): void
    {
        config()->set('services.tracking.demo', false);
        $car = $this->car();
        $b = $this->booking($car);
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => -0.5, 'longitude' => 117.15, 'speed' => 20, 'course' => 90, 'device_time' => '2026-08-11 08:00:00']);
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => -0.49, 'longitude' => 117.16, 'speed' => 30, 'course' => 90, 'device_time' => '2026-08-11 09:00:00']);
        // Outside the rental window — must be excluded.
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => 0.0, 'longitude' => 100.0, 'speed' => 0, 'course' => 0, 'device_time' => '2026-09-01 09:00:00']);

        $res = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertCount(2, $res->json('points'));
        $this->assertSame(-0.5, $res->json('points.0.lat'));
    }

    public function test_replay_fabricates_when_demo_and_empty(): void
    {
        config()->set('services.tracking.demo', true);
        $b = $this->booking($this->car());
        $owner = $this->owner();

        $res = $this->actingAs($owner)->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertNotEmpty($res->json('points'));
        // Deterministic: same booking → same first point on a second call.
        $res2 = $this->actingAs($owner)->getJson("/admin/bookings/{$b->id}/replay");
        $this->assertSame($res->json('points.0'), $res2->json('points.0'));
    }

    public function test_replay_empty_when_demo_off_and_no_data(): void
    {
        config()->set('services.tracking.demo', false);
        $b = $this->booking($this->car());

        $res = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertSame([], $res->json('points'));
    }

    public function test_replay_cross_tenant_404(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        app(TenantManager::class)->set($other);
        $otherCar = $this->car();
        $otherBooking = $this->booking($otherCar);
        app(TenantManager::class)->set($this->tenant);

        $this->actingAs($this->owner())->getJson("/admin/bookings/{$otherBooking->id}/replay")
            ->assertNotFound();
    }

    public function test_show_page_has_replay_when_car_present(): void
    {
        $b = $this->booking($this->car());
        $res = $this->actingAs($this->owner())->get("/admin/bookings/{$b->id}");
        $res->assertOk();
        $res->assertSee('booking-replay.js', false);
        $res->assertSee('Replay Perjalanan', false);
    }
}
