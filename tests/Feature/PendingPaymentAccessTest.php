<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingPaymentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_pending_payment_owner_cannot_access_admin_dashboard(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
        ]);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@pending-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        // Tenant terkunci tidak di-403 buntu, tapi diarahkan ke halaman bayar.
        $this->actingAs($owner)->get('/admin')->assertRedirect(route('admin.subscription.index'));
    }

    public function test_active_tenant_owner_can_access_admin_dashboard(): void
    {
        $tenant = Tenant::create([
            'name' => 'Active Co', 'slug' => 'active-co', 'plan' => 'pro',
            'subscription_status' => 'active',
        ]);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@active-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/admin')->assertOk();
    }

    public function test_trial_tenant_owner_can_still_access_admin_dashboard(): void
    {
        $tenant = Tenant::create([
            'name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'business',
            'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(10),
        ]);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@trial-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/admin')->assertOk();
    }
}
