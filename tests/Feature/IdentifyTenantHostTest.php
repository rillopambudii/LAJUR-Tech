<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentifyTenantHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipv4_host_does_not_query_tenant_by_ip_label_and_falls_back_to_default(): void
    {
        // A tenant literally named "127" would previously be matched by
        // accident when developing against http://127.0.0.1 — assert the
        // default 'lajur' tenant resolves instead, proving the IP address
        // is never misread as a subdomain slug.
        Tenant::create(['name' => 'Fake', 'slug' => '127', 'plan' => 'basic', 'subscription_status' => 'active']);

        $response = $this->get('http://127.0.0.1/');

        $response->assertOk();
        // The real default storefront (Lajur) renders, not the "127" decoy tenant.
        $response->assertSee('Lajur');
    }

    public function test_slug_dot_localhost_resolves_the_tenant_storefront(): void
    {
        Tenant::create([
            'name' => 'Ucup Rental', 'slug' => 'ucupadhy', 'plan' => 'basic',
            'subscription_status' => 'active', 'display_name' => 'Ucup Rental Mobil',
        ]);

        $response = $this->get('http://ucupadhy.localhost/');

        $response->assertOk();
        $response->assertSee('Ucup Rental Mobil');
    }

    public function test_genuine_subdomain_still_resolves_the_matching_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active', 'display_name' => 'Kaltim Rental Mobil',
        ]);

        $response = $this->get('http://kaltim-rental.example.test/');

        $response->assertOk();
        $response->assertSee('Kaltim Rental Mobil');
    }
}
