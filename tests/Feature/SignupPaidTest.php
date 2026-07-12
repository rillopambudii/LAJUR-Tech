<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignupPaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    public function test_paid_form_shows_plan_name_and_price(): void
    {
        $this->get('/daftar/pro')->assertOk()->assertSee('Pro');
    }

    public function test_paid_signup_creates_pending_tenant_and_redirects_to_midtrans(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/subxyz',
            ]),
        ]);

        $response = $this->post('/daftar/pro', [
            'business_name' => 'Bayar Co', 'slug' => 'bayar-co',
            'owner_name' => 'Sari', 'email' => 'sari@bayar-co.id', 'password' => 'password123',
        ]);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/subxyz');

        $tenant = Tenant::where('slug', 'bayar-co')->firstOrFail();
        $this->assertSame('pro', $tenant->plan);
        $this->assertSame('pending_payment', $tenant->subscription_status);
        $this->assertNotNull($tenant->payment_ref);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['email' => 'sari@bayar-co.id']);
    }

    public function test_finish_page_shows_pending_when_not_yet_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-1-999',
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-1-999')
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_finish_page_shows_success_when_active(): void
    {
        $tenant = Tenant::create([
            'name' => 'Paid Co', 'slug' => 'paid-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'payment_ref' => 'LAJUR-SUB-2-999',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-2-999')
            ->assertOk()
            ->assertSee('Login');
    }
}
