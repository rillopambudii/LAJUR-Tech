<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGatingSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function ownerFor(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_basic_plan_tenant_does_not_see_gated_nav_items(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Pelacakan');
        $response->assertDontSee('Asisten AI');
    }

    public function test_basic_plan_tenant_is_redirected_away_from_tracking(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co 2', 'slug' => 'basic-co-2', 'plan' => 'basic', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/admin/tracking')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_business_plan_tenant_sees_gated_nav_items(): void
    {
        $tenant = Tenant::create(['name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active']);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin');

        $response->assertOk();
        $response->assertSee('Pelacakan');
        $response->assertSee('Asisten AI');
    }

    public function test_tenant_with_expired_trial_loses_gated_route_access_on_next_request(): void
    {
        $tenant = Tenant::create([
            'name' => 'Expiring Co', 'slug' => 'expiring-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/admin/tracking')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('basic', $tenant->fresh()->plan);
    }
}
