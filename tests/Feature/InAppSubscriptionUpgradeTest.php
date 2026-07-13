<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InAppSubscriptionUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Existing Co', 'slug' => 'existing-co', 'plan' => 'basic',
            'subscription_status' => 'active',
        ]);
        app(TenantManager::class)->set($this->tenant);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@existing-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_subscription_page_reachable_on_basic_plan(): void
    {
        $this->actingAs($this->owner())->get('/admin/langganan')
            ->assertOk()
            ->assertSee('Pro')
            ->assertSee('Business');
    }

    public function test_choosing_a_plan_sets_pending_plan_without_changing_active_plan(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/upgrade123',
            ]),
        ]);

        $response = $this->actingAs($this->owner())->post('/admin/langganan/pro');

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/upgrade123');

        $this->tenant->refresh();
        $this->assertSame('basic', $this->tenant->plan); // untouched until webhook confirms
        $this->assertSame('pro', $this->tenant->pending_plan);
        $this->assertNotNull($this->tenant->payment_ref);
    }

    public function test_failed_checkout_rolls_back_pending_plan(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([], 500),
        ]);

        $this->actingAs($this->owner())->post('/admin/langganan/pro');

        $this->tenant->refresh();
        $this->assertNull($this->tenant->pending_plan);
        $this->assertNull($this->tenant->payment_ref);
    }

    public function test_driver_cannot_access_subscription_page(): void
    {
        $driver = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sopir', 'email' => 'd@existing-co.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER,
        ]);

        $this->actingAs($driver)->get('/admin/langganan')->assertForbidden();
    }

    public function test_finish_page_shows_processing_while_pending(): void
    {
        $this->tenant->update(['pending_plan' => 'pro', 'payment_ref' => 'LAJUR-SUB-x-1']);

        $this->actingAs($this->owner())->get('/admin/langganan/selesai')
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_finish_page_shows_success_when_activated(): void
    {
        $this->tenant->update(['plan' => 'pro', 'pending_plan' => null]);

        $this->actingAs($this->owner())->get('/admin/langganan/selesai')
            ->assertOk()
            ->assertSee('aktif');
    }
}
