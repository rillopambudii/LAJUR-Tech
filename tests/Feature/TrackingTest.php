<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function carWithPosition(Tenant $t, string $name, float $lat, float $lng): Car
    {
        app(TenantManager::class)->set($t);
        $car = Car::create([
            'name' => $name, 'brand' => 'X', 'type' => 'MPV', 'transmission' => 'Automatic',
            'fuel_type' => 'Bensin', 'seats' => 5, 'price_per_day' => 100000,
        ]);
        VehiclePosition::create([
            'car_id' => $car->id, 'latitude' => $lat, 'longitude' => $lng,
            'speed' => 10, 'course' => 90, 'device_time' => now(),
        ]);
        app(TenantManager::class)->set($this->tenant);

        return $car;
    }

    public function test_tracking_page_loads(): void
    {
        config()->set('services.google.maps_key', 'test-key');
        // Pin demo off: the demo (Leaflet) path intentionally takes precedence
        // over Google Maps, and it omits the history panel. This test exercises
        // the production Google Maps rendering.
        config()->set('services.tracking.demo', false);

        $this->actingAs($this->owner)->get('/admin/tracking')
            ->assertOk()
            ->assertSee('Rute Histori');
    }

    public function test_live_positions_are_tenant_scoped(): void
    {
        $this->carWithPosition($this->tenant, 'Innova', -0.5, 117.15);
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $this->carWithPosition($other, 'Avanza', 1.0, 100.0);

        $res = $this->actingAs($this->owner)->getJson('/admin/tracking/live');
        $res->assertOk();

        $names = collect($res->json('positions'))->pluck('name');
        $this->assertTrue($names->contains('Innova'));
        $this->assertFalse($names->contains('Avanza'));
    }

    public function test_history_returns_points_in_range(): void
    {
        $car = $this->carWithPosition($this->tenant, 'Innova', -0.5, 117.15);

        $res = $this->actingAs($this->owner)->getJson(
            '/admin/tracking/history?car='.$car->id
            .'&from='.now()->subDay()->toDateString()
            .'&to='.now()->addDay()->toDateString()
        );
        $res->assertOk();
        $this->assertNotEmpty($res->json('points'));
    }

    public function test_demo_mode_fabricates_positions_when_empty(): void
    {
        config()->set('services.tracking.demo', true);
        Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000, 'is_available' => true,
        ]);

        $res = $this->actingAs($this->owner)->getJson('/admin/tracking/live');
        $res->assertOk()->assertJson(['demo' => true]);
        $this->assertNotEmpty($res->json('positions'));
    }
}
