<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_creating_a_tenant_starts_a_14_day_business_trial(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/superadmin/tenants', ['name' => 'Rental Baru', 'slug' => 'rental-baru'])
            ->assertRedirect();

        $tenant = Tenant::where('slug', 'rental-baru')->firstOrFail();

        $this->assertSame('business', $tenant->plan);
        $this->assertSame('trial', $tenant->subscription_status);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_super_admin_can_change_a_tenants_plan(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/tenants/{$tenant->id}/plan", ['plan' => 'basic'])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame('basic', $tenant->plan);
        $this->assertSame('active', $tenant->subscription_status);
    }

    public function test_super_admin_can_change_a_tenants_status(): void
    {
        $tenant = Tenant::create(['name' => 'Rental X', 'slug' => 'rental-x', 'plan' => 'business', 'subscription_status' => 'active']);

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/tenants/{$tenant->id}/status", ['subscription_status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->subscription_status);
    }

    public function test_status_change_rejects_invalid_value(): void
    {
        $tenant = Tenant::create(['name' => 'Rental X', 'slug' => 'rental-x', 'plan' => 'business', 'subscription_status' => 'active']);

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/tenants/{$tenant->id}/status", ['subscription_status' => 'bogus'])
            ->assertSessionHasErrors('subscription_status');

        $this->assertSame('active', $tenant->fresh()->subscription_status);
    }

    public function test_non_super_admin_cannot_change_status(): void
    {
        $tenant = Tenant::create(['name' => 'Rental X', 'slug' => 'rental-x', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@x.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)
            ->patch("/superadmin/tenants/{$tenant->id}/status", ['subscription_status' => 'suspended'])
            ->assertForbidden();
    }

    public function test_super_admin_can_delete_a_tenant_and_all_its_scoped_data(): void
    {
        $tenant = Tenant::create(['name' => 'Rental X', 'slug' => 'rental-x', 'plan' => 'business', 'subscription_status' => 'active']);

        User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@x.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
        $carId = DB::table('cars')->insertGetId([
            'tenant_id' => $tenant->id, 'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 300000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bookings')->insert([
            'tenant_id' => $tenant->id, 'car_id' => $carId, 'car_name' => 'Avanza', 'customer_name' => 'A',
            'customer_email' => 'a@a.id', 'customer_phone' => '08', 'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(), 'days' => 1, 'price_per_day' => 300000,
            'total_price' => 300000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fuel_logs')->insert([
            'tenant_id' => $tenant->id, 'car_id' => $carId, 'filled_at' => now(), 'liters' => 10,
            'price_per_liter' => 10000, 'total_cost' => 100000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vehicle_positions')->insert([
            'tenant_id' => $tenant->id, 'car_id' => $carId, 'latitude' => 1.1, 'longitude' => 2.2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('car_mileage_daily')->insert([
            'tenant_id' => $tenant->id, 'car_id' => $carId, 'date' => now()->toDateString(), 'km' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('contact_messages')->insert([
            'tenant_id' => $tenant->id, 'name' => 'A', 'email' => 'a@a.id', 'message' => 'hi',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('testimonials')->insert([
            'tenant_id' => $tenant->id, 'name' => 'A', 'quote' => 'good',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->delete("/superadmin/tenants/{$tenant->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        foreach (['bookings', 'users', 'car_mileage_daily', 'cars', 'contact_messages', 'fuel_logs', 'testimonials', 'vehicle_positions'] as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $tenant->id)->count(), "$table still has rows for deleted tenant");
        }
    }

    public function test_deleting_the_default_lajur_tenant_is_blocked(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->delete("/superadmin/tenants/{$tenant->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_non_super_admin_cannot_delete_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Rental X', 'slug' => 'rental-x', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@x.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)
            ->delete("/superadmin/tenants/{$tenant->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }
}
