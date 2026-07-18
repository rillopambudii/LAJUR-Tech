<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_pricing_page_lists_plans_from_database(): void
    {
        $response = $this->get('/daftar');

        $response->assertOk();
        $response->assertSee('Basic');
        $response->assertSee('Pro');
        $response->assertSee('Business');
        $response->assertSee('Coba Gratis');
    }

    public function test_trial_signup_creates_tenant_and_logs_in(): void
    {
        $this->post('/daftar/trial', [
            'business_name' => 'Rental Baru', 'slug' => 'rental-baru',
            'owner_name' => 'Budi', 'email' => 'budi@rental-baru.id',
            'password' => 'password123', 'agree' => '1',
        ])->assertRedirect(route('admin.dashboard'));

        $tenant = Tenant::where('slug', 'rental-baru')->firstOrFail();
        $this->assertSame('business', $tenant->plan);
        $this->assertSame('trial', $tenant->subscription_status);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));

        $user = User::where('email', 'budi@rental-baru.id')->firstOrFail();
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_trial_signup_requires_agreeing_to_terms(): void
    {
        $this->post('/daftar/trial', [
            'business_name' => 'Tanpa Setuju', 'slug' => 'tanpa-setuju',
            'owner_name' => 'Budi', 'email' => 'budi@tanpa-setuju.id',
            'password' => 'password123', // tanpa 'agree'
        ])->assertSessionHasErrors('agree');

        $this->assertDatabaseMissing('tenants', ['slug' => 'tanpa-setuju']);
    }

    public function test_trial_signup_rejects_duplicate_slug(): void
    {
        Tenant::where('slug', 'lajur')->firstOrFail();

        $this->post('/daftar/trial', [
            'business_name' => 'Dupe', 'slug' => 'lajur',
            'owner_name' => 'Budi', 'email' => 'budi2@x.id', 'password' => 'password123',
        ])->assertSessionHasErrors('slug');
    }
}
