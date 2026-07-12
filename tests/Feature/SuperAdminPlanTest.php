<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPlanTest extends TestCase
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

    public function test_non_super_admin_cannot_access_plans_page(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/superadmin/plans')->assertForbidden();
    }

    public function test_super_admin_can_update_plan_price_and_trial_days(): void
    {
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/plans/{$plan->id}", ['price' => 399000, 'trial_days' => 14])
            ->assertRedirect();

        $this->assertSame(399000, $plan->fresh()->price);
    }

    public function test_super_admin_can_toggle_plan_features(): void
    {
        $plan = Plan::where('key', 'basic')->firstOrFail();
        $feature = Feature::where('key', 'gps_tracking')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/plans/{$plan->id}/features", ['features' => [$feature->id]])
            ->assertRedirect();

        $this->assertTrue($plan->fresh()->features->contains('key', 'gps_tracking'));
    }
}
