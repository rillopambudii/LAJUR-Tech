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

    public function test_super_admin_can_set_and_clear_discount(): void
    {
        $plan = Plan::where('key', 'pro')->firstOrFail();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch("/superadmin/plans/{$plan->id}", [
                'price' => $plan->price, 'trial_days' => $plan->trial_days,
                'discount_price' => 999000, 'discount_label' => 'Promo Peluncuran',
            ])->assertRedirect();

        $plan->refresh();
        $this->assertTrue($plan->hasDiscount());
        $this->assertSame(999000, $plan->effectivePrice());

        // Kosongkan diskon → label ikut terhapus, harga kembali normal.
        $this->actingAs($admin)
            ->patch("/superadmin/plans/{$plan->id}", [
                'price' => $plan->price, 'trial_days' => $plan->trial_days,
            ])->assertRedirect();

        $plan->refresh();
        $this->assertFalse($plan->hasDiscount());
        $this->assertNull($plan->discount_label);
        $this->assertSame($plan->price, $plan->effectivePrice());
    }

    public function test_discount_must_be_below_normal_price(): void
    {
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch("/superadmin/plans/{$plan->id}", [
                'price' => $plan->price, 'trial_days' => $plan->trial_days,
                'discount_price' => $plan->price + 1000,
            ])->assertSessionHasErrors('discount_price');
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
