<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function brandedTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active',
            'display_name' => 'Kaltim Rental Mobil',
            'tagline' => 'Sewa Mobil Samarinda Terpercaya',
            'contact_phone' => '+62 811-1111-2222',
            'contact_address' => 'Jl. Juanda No. 1, Samarinda',
            'contact_email' => 'halo@kaltimrental.id',
            'accent_color' => '#2C6E8F',
        ]);
    }

    private function ownerOf(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER,
        ]);
    }

    public function test_default_tenant_home_still_shows_lajur_branding(): void
    {
        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertSee('Lajur');
        $response->assertSee('Samarinda, Kalimantan Timur');
        $response->assertSee('halo@lajur.id');
    }

    public function test_home_shows_tenant_branding_when_logged_in_as_its_owner(): void
    {
        $tenant = $this->brandedTenant();

        $response = $this->actingAs($this->ownerOf($tenant))->get('/demo');

        $response->assertOk();
        $response->assertSee('Kaltim Rental Mobil');
        $response->assertSee('Sewa Mobil Samarinda Terpercaya');
        $response->assertSee('halo@kaltimrental.id');
        $response->assertSee('#2C6E8F', false); // accent style override present
    }

    public function test_accent_style_absent_when_not_set(): void
    {
        $this->get('/demo')->assertOk()->assertDontSee('--accent-override', false);
    }

    public function test_branding_does_not_leak_across_tenants(): void
    {
        $this->brandedTenant(); // exists, but request runs under default lajur tenant

        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertDontSee('Kaltim Rental Mobil');
    }
}
