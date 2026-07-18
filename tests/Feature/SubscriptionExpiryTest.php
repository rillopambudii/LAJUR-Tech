<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TrialGuard;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_lapsed_paid_subscription_is_locked(): void
    {
        $tenant = Tenant::create([
            'name' => 'Lapsed Co', 'slug' => 'lapsed-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->subDay(),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        // Langganan berbayar yang lewat masa diperlakukan sama dgn trial: dikunci.
        $this->assertSame('suspended', $tenant->fresh()->subscription_status);
    }

    public function test_active_unexpired_subscription_is_untouched(): void
    {
        $tenant = Tenant::create([
            'name' => 'Fresh Co', 'slug' => 'fresh-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->addDays(10),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        $this->assertSame('pro', $tenant->fresh()->plan);
    }

    public function test_tenant_with_no_subscription_ends_at_is_untouched(): void
    {
        // e.g. the seeded 'lajur' tenant or any tenant that came from the trial path.
        $tenant = Tenant::create([
            'name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(5),
        ]);

        app(TrialGuard::class)->settleIfLapsed($tenant);

        $this->assertSame('business', $tenant->fresh()->plan);
    }

    public function test_check_trial_command_also_locks_lapsed_subscriptions(): void
    {
        Tenant::create([
            'name' => 'Lapsed Co 2', 'slug' => 'lapsed-co-2', 'plan' => 'business',
            'subscription_status' => 'active', 'subscription_ends_at' => now()->subDay(),
        ]);

        $this->artisan('tenants:check-trial')->assertExitCode(0);

        $this->assertSame('suspended', Tenant::where('slug', 'lapsed-co-2')->value('subscription_status'));
    }
}
