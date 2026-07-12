<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
