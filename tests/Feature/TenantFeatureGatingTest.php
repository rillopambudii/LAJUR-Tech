<?php

namespace Tests\Feature;

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

    public function test_pro_plan_has_tracking_fuel_export_but_not_ai(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pro Co', 'slug' => 'pro-co', 'plan' => 'pro', 'subscription_status' => 'active',
        ]);

        $this->assertTrue($tenant->hasFeature('gps_tracking'));
        $this->assertTrue($tenant->hasFeature('fuel_tracking'));
        $this->assertTrue($tenant->hasFeature('export'));
        $this->assertFalse($tenant->hasFeature('ai_assistant'));
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
}
