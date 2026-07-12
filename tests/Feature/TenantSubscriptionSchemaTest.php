<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSubscriptionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_store_payment_ref_and_subscription_ends_at(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
            'payment_ref' => 'LAJUR-SUB-999-1234567890',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->assertSame('LAJUR-SUB-999-1234567890', $tenant->fresh()->payment_ref);
        $this->assertTrue($tenant->fresh()->subscription_ends_at->isFuture());
        $this->assertContains('pending_payment', Tenant::STATUSES);
    }

    public function test_payment_ref_is_unique(): void
    {
        Tenant::create([
            'name' => 'A', 'slug' => 'a-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-DUP',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tenant::create([
            'name' => 'B', 'slug' => 'b-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-DUP',
        ]);
    }
}
