<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);

        Route::middleware(['web', 'auth', 'feature:ai_assistant'])
            ->get('/__test/gated', fn () => 'ok');
    }

    private function ownerFor(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_blocks_when_tenant_plan_lacks_the_feature(): void
    {
        $tenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/__test/gated')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_allows_when_tenant_plan_includes_the_feature(): void
    {
        $tenant = Tenant::create(['name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active']);

        $this->actingAs($this->ownerFor($tenant))
            ->get('/__test/gated')
            ->assertOk();
    }
}
