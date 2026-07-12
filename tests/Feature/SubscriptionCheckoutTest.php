<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Payments\SubscriptionCheckout;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function pendingTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
        ]);
    }

    public function test_creates_checkout_and_sets_payment_ref(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/sub123',
            ]),
        ]);

        $tenant = $this->pendingTenant();
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $url = app(SubscriptionCheckout::class)->createCheckout($tenant, $plan);

        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/sub123', $url);
        $tenant->refresh();
        $this->assertStringStartsWith('LAJUR-SUB-'.$tenant->id.'-', $tenant->payment_ref);
    }

    public function test_returns_null_when_server_key_unset(): void
    {
        config()->set('services.midtrans.server_key', '');

        $tenant = $this->pendingTenant();
        $plan = Plan::where('key', 'pro')->firstOrFail();

        $this->assertNull(app(SubscriptionCheckout::class)->createCheckout($tenant, $plan));
    }
}
