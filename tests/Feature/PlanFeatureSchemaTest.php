<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanFeatureSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_and_features_tables_support_many_to_many(): void
    {
        $planId = DB::table('plans')->insertGetId([
            'key' => 'test-plan', 'name' => 'Test', 'price' => 1000, 'trial_days' => 14,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $featureId = DB::table('features')->insertGetId([
            'key' => 'test-feature', 'name' => 'Test Feature',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('feature_plan')->insert(['plan_id' => $planId, 'feature_id' => $featureId]);

        $this->assertDatabaseHas('feature_plan', ['plan_id' => $planId, 'feature_id' => $featureId]);
    }

    public function test_lajur_tenant_is_migrated_to_business_plan(): void
    {
        $plan = DB::table('tenants')->where('slug', 'lajur')->value('plan');

        $this->assertSame('business', $plan);
    }
}
