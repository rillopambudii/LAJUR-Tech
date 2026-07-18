<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFeatureGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_basic_plan_has_no_premium_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active',
        ]);

        $this->assertFalse($tenant->hasFeature('gps_tracking'));
        $this->assertFalse($tenant->hasFeature('ai_assistant'));
    }

    public function test_pro_plan_has_ai_and_export_but_not_fuel_or_gps(): void
    {
        // Struktur decoy: Pro cuma menambah AI di atas Basic; BBM & GPS milik Business.
        $tenant = Tenant::create([
            'name' => 'Pro Co', 'slug' => 'pro-co', 'plan' => 'pro', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('ai_assistant'));
        $this->assertTrue($tenant->hasFeature('export'));
        $this->assertFalse($tenant->hasFeature('fuel_tracking'));
        $this->assertFalse($tenant->hasFeature('gps_tracking'));
    }

    public function test_business_plan_has_all_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Biz Co', 'slug' => 'biz-co', 'plan' => 'business', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('gps_tracking'));
        $this->assertTrue($tenant->hasFeature('fuel_tracking'));
        $this->assertTrue($tenant->hasFeature('export'));
        $this->assertTrue($tenant->hasFeature('ai_assistant'));
    }

    public function test_current_plan_cache_is_invalidated_when_plan_changes_on_same_instance(): void
    {
        $tenant = Tenant::create([
            'name' => 'Cache Co', 'slug' => 'cache-co', 'plan' => 'business', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('ai_assistant'));

        $tenant->update(['plan' => 'basic']);

        $this->assertFalse($tenant->hasFeature('ai_assistant'));
    }

    public function test_reseeding_plans_does_not_clobber_manual_superadmin_edits(): void
    {
        $plan = Plan::where('key', 'pro')->firstOrFail();
        $plan->update(['price' => 999999]);

        $exportFeatureId = Feature::where('key', 'export')->value('id');
        $plan->features()->detach($exportFeatureId);

        $this->seed(PlanSeeder::class);

        $plan->refresh();
        $this->assertSame(999999, $plan->price);
        $this->assertFalse($plan->features()->where('key', 'export')->exists());
    }
}
