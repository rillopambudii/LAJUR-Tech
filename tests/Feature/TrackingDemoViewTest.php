<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingDemoViewTest extends TestCase
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

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_admin_tracking_uses_leaflet_demo_when_demo_on(): void
    {
        config()->set('services.tracking.demo', true);
        config()->set('services.google.maps_key', null);

        $res = $this->actingAs($this->owner())->get('/admin/tracking');

        $res->assertOk();
        $res->assertSee('tracking-demo.js', false);
        $res->assertSee('/vendor/leaflet/leaflet.js', false);
        $res->assertDontSee('maps.googleapis.com', false);
    }

    public function test_customer_lacak_shows_demo_map_and_eta_when_demo_on(): void
    {
        config()->set('services.tracking.demo', true);

        $car = Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081234567',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'pending',
            'trip_status' => Booking::TRIP_NOT_STARTED,
            'booking_code' => Booking::generateBookingCode(),
        ]);

        $res = $this->get('/lacak/'.$booking->booking_code);

        $res->assertOk();
        $res->assertSee('tracking-demo.js', false);
        $res->assertSee('data-eta', false);
    }
}
