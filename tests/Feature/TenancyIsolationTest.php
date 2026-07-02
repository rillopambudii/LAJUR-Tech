<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeCar(array $overrides = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Car',
            'brand' => 'Brand',
            'type' => 'SUV',
            'transmission' => 'Automatic',
            'fuel_type' => 'Bensin',
            'seats' => 4,
            'price_per_day' => 100,
        ], $overrides));
    }

    public function test_queries_are_scoped_to_the_active_tenant(): void
    {
        $a = $this->makeTenant('rental-a');
        $b = $this->makeTenant('rental-b');

        $manager = app(TenantManager::class);

        $manager->set($a);
        $this->makeCar(['name' => 'Car A']);

        $manager->set($b);
        $this->makeCar(['name' => 'Car B']);

        // Each tenant only sees its own row.
        $manager->set($a);
        $this->assertSame(1, Car::count());
        $this->assertSame('Car A', Car::first()->name);

        $manager->set($b);
        $this->assertSame(1, Car::count());
        $this->assertSame('Car B', Car::first()->name);
    }

    public function test_tenant_id_is_auto_filled_on_create(): void
    {
        $a = $this->makeTenant('rental-a');
        app(TenantManager::class)->set($a);

        $car = $this->makeCar(['name' => 'Auto']);

        $this->assertSame($a->id, $car->tenant_id);
    }

    public function test_without_context_no_scope_is_applied(): void
    {
        $a = $this->makeTenant('rental-a');
        $manager = app(TenantManager::class);

        $manager->set($a);
        $this->makeCar(['name' => 'Car A']);

        // No tenant context => original single-tenant behaviour (sees all rows).
        $manager->set(null);
        $this->assertSame(1, Car::count());
    }
}
