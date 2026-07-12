<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_expired_trial_is_downgraded_to_basic(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->subDay(),
        ]);

        app(TrialGuard::class)->settleIfExpired($tenant);

        $this->assertSame('basic', $tenant->fresh()->plan);
        $this->assertSame('active', $tenant->fresh()->subscription_status);
    }

    public function test_active_trial_is_left_untouched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co 2', 'slug' => 'trial-co-2', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(5),
        ]);

        app(TrialGuard::class)->settleIfExpired($tenant);

        $this->assertSame('business', $tenant->fresh()->plan);
        $this->assertSame('trial', $tenant->fresh()->subscription_status);
    }

    public function test_check_trial_command_downgrades_all_expired_tenants(): void
    {
        Tenant::create([
            'name' => 'Expired Co', 'slug' => 'expired-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('tenants:check-trial')->assertExitCode(0);

        $this->assertSame('basic', Tenant::where('slug', 'expired-co')->value('plan'));
    }
}
