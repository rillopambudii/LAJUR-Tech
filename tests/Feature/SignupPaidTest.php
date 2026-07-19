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
            'agree' => '1',
        ]);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/subxyz');

        $tenant = Tenant::where('slug', 'bayar-co')->firstOrFail();
        $this->assertSame('pro', $tenant->plan);
        $this->assertSame('pending_payment', $tenant->subscription_status);
        $this->assertNotNull($tenant->payment_ref);

        // Pemilik login otomatis: sesi bertahan lewat Midtrans, jadi saat kembali
        // ke halaman "selesai" mereka sudah masuk dan langsung ke dashboard.
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'sari@bayar-co.id']);
    }

    public function test_finish_activates_subscription_and_redirects_to_dashboard_when_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Bayar Co', 'slug' => 'bayar-co', 'plan' => 'basic',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-1-999',
        ]);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Sari', 'email' => 'sari@bayar-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        // Midtrans mengonfirmasi pembayaran (verifikasi server-to-server).
        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'transaction_status' => 'settlement', 'fraud_status' => 'accept',
            ]),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-1-999')
            ->assertRedirect(route('admin.dashboard'));

        $tenant->refresh();
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertNotNull($tenant->subscription_ends_at);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_finish_stays_pending_when_payment_not_completed(): void
    {
        $tenant = Tenant::create([
            'name' => 'Belum Co', 'slug' => 'belum-co', 'plan' => 'basic',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-2-888',
        ]);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'transaction_status' => 'pending', 'fraud_status' => 'accept',
            ]),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-2-888')->assertOk();

        $this->assertSame('pending_payment', $tenant->fresh()->subscription_status);
        $this->assertGuest();
    }

    public function test_failed_checkout_does_not_leave_orphaned_tenant_or_user(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([], 500),
        ]);

        $this->post('/daftar/pro', [
            'business_name' => 'Fail Co', 'slug' => 'fail-co',
            'owner_name' => 'Rudi', 'email' => 'rudi@fail-co.id', 'password' => 'password123',
            'agree' => '1',
        ]);

        $this->assertDatabaseMissing('tenants', ['slug' => 'fail-co']);
        $this->assertDatabaseMissing('users', ['email' => 'rudi@fail-co.id']);
    }

    public function test_finish_page_shows_pending_when_not_yet_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-1-999',
        ]);

        // Midtrans belum menerima pembayaran → tetap menunggu.
        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'transaction_status' => 'pending', 'fraud_status' => 'accept',
            ]),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-1-999')
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_finish_redirects_active_tenant_to_dashboard(): void
    {
        // Sudah aktif (mis. webhook lebih dulu tiba) → langsung ke dashboard,
        // tak perlu klik "Login" manual.
        $tenant = Tenant::create([
            'name' => 'Paid Co', 'slug' => 'paid-co', 'plan' => 'pro',
            'subscription_status' => 'active', 'payment_ref' => 'LAJUR-SUB-2-999',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->get('/daftar/selesai?order_id=LAJUR-SUB-2-999')
            ->assertRedirect(route('admin.dashboard'));
    }
}
